<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AppAuthController extends Controller
{
    public static function ensureCoreTables(): void
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `sync_collections` (
            `collection_name` varchar(64) NOT NULL,
            `doc_id` varchar(128) NOT NULL,
            `data_json` longtext NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`collection_name`, `doc_id`),
            KEY `collection_idx` (`collection_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        DB::statement("CREATE TABLE IF NOT EXISTS `app_settings` (
            `setting_key` varchar(100) NOT NULL,
            `setting_value` longtext NOT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function getAppSetting(string $key, ?string $default = null): ?string
    {
        $row = DB::table('app_settings')->where('setting_key', $key)->first();
        return $row ? (string) $row->setting_value : $default;
    }

    public static function setAppSetting(string $key, string $value): void
    {
        DB::statement(
            'INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)',
            [$key, $value]
        );
    }

    public static function getAuthSecret(): string
    {
        $secret = self::getAppSetting('auth_hmac_secret');
        if (!$secret) {
            $appKey = (string) config('app.key', '');
            $secret = $appKey ? hash('sha256', $appKey) : bin2hex(random_bytes(32));
            self::setAppSetting('auth_hmac_secret', $secret);
        }
        return $secret;
    }

    public static function issueAuthToken(string $email, string $displayName, string $role = 'owner'): string
    {
        $secret = self::getAuthSecret();
        $payload = base64_encode((string) json_encode([
            'email' => $email,
            'displayName' => $displayName,
            'role' => $role,
            'iat' => time(),
            'exp' => time() + (86400 * 30),
        ], JSON_UNESCAPED_UNICODE));

        $sig = hash_hmac('sha256', $payload, $secret);
        return $payload . '.' . $sig;
    }

    public static function extractToken(Request $request): ?string
    {
        $xAuth = $request->header('X-Auth-Token');
        if ($xAuth) {
            return trim($xAuth);
        }

        $bearer = $request->bearerToken();
        if ($bearer) {
            return trim($bearer);
        }

        $serverAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($serverAuth && preg_match('/Bearer\s+(.+)/i', $serverAuth, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    public static function verifyAuthToken(?string $token): ?array
    {
        if (!$token || !str_contains($token, '.')) {
            return null;
        }

        [$payloadB64, $sig] = explode('.', $token, 2);
        $secret = self::getAuthSecret();
        $expected = hash_hmac('sha256', $payloadB64, $secret);

        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $decoded = json_decode((string) base64_decode($payloadB64), true);
        if (!is_array($decoded) || empty($decoded['email'])) {
            return null;
        }

        if (!empty($decoded['exp']) && time() > (int) $decoded['exp']) {
            return null;
        }

        return $decoded;
    }

    public static function getAuthenticatedUser(Request $request): ?array
    {
        self::ensureCoreTables();
        $token = self::extractToken($request);
        return self::verifyAuthToken($token);
    }

    public function handle(Request $request): JsonResponse
    {
        $action = (string) ($request->query('action') ?: $request->input('action', 'status'));

        if ($request->isMethod('get') && $action === 'status') {
            return $this->status($request);
        }
        if ($request->isMethod('post') && $action === 'setup') {
            return $this->setup($request);
        }
        if ($request->isMethod('post') && $action === 'login') {
            return $this->login($request);
        }
        if ($request->isMethod('post') && $action === 'change-password') {
            return $this->changePassword($request);
        }

        return response()->json(['success' => false, 'error' => 'Invalid auth action'], 400);
    }

    public function status(Request $request): JsonResponse
    {
        try {
            self::ensureCoreTables();
            $ownerEmail = self::getAppSetting('admin_email', 'samiulbiswas016@gmail.com');
            $passwordHash = self::getAppSetting('admin_password_hash');
            $user = self::verifyAuthToken(self::extractToken($request));

            return response()->json([
                'success' => true,
                'hasPassword' => !empty($passwordHash),
                'ownerEmail' => $ownerEmail,
                'authenticated' => $user !== null,
                'user' => $user ? [
                    'uid' => 'cpanel-' . md5(strtolower($user['email'])),
                    'email' => $user['email'],
                    'displayName' => $user['displayName'] ?? 'Admin',
                    'role' => $user['role'] ?? 'owner',
                ] : null,
            ]);
        } catch (Throwable $e) {
            $errDetail = config('app.debug') ? ' (' . $e->getMessage() . ')' : '';
            return response()->json([
                'success' => false,
                'code' => 'DB_CONNECTION_ERROR',
                'error' => 'MySQL ডাটাবেস সংযোগ হয়নি। অনুগ্রহ করে .env ফাইলে তথ্য যাচাই করুন।' . $errDetail,
            ], 500);
        }
    }

    public function setup(Request $request): JsonResponse
    {
        try {
            self::ensureCoreTables();
            $ownerEmail = (string) self::getAppSetting('admin_email', 'samiulbiswas016@gmail.com');
            $passwordHash = self::getAppSetting('admin_password_hash');

            if (!empty($passwordHash)) {
                return response()->json([
                    'success' => false,
                    'error' => 'অ্যাডমিন পাসওয়ার্ড আগে থেকেই সেট করা আছে। অনুগ্রহ করে লগইন করুন।',
                ], 400);
            }

            $email = strtolower(trim((string) $request->input('email', $ownerEmail)));
            $password = (string) $request->input('password', '');
            $displayName = trim((string) $request->input('displayName', 'Samiul Biswas'));

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'error' => 'সঠিক ইমেইল দিন।'], 400);
            }
            if (strlen($password) < 4) {
                return response()->json(['success' => false, 'error' => 'কমপক্ষে ৪ অক্ষরের পাসওয়ার্ড দিন।'], 400);
            }

            self::setAppSetting('admin_email', $email);
            self::setAppSetting('admin_display_name', $displayName);
            self::setAppSetting('admin_password_hash', password_hash($password, PASSWORD_DEFAULT));

            $token = self::issueAuthToken($email, $displayName, 'owner');

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'uid' => 'cpanel-' . md5($email),
                    'email' => $email,
                    'displayName' => $displayName,
                    'role' => 'owner',
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $throttleKey = 'login_attempt:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => false,
                'code' => 'TOO_MANY_ATTEMPTS',
                'error' => "খুব বেশি বার ভুল পাসওয়ার্ড দেওয়া হয়েছে। অনুগ্রহ করে {$seconds} সেকেন্ড পর আবার চেষ্টা করুন।",
            ], 429);
        }

        try {
            self::ensureCoreTables();
            $ownerEmail = (string) self::getAppSetting('admin_email', 'samiulbiswas016@gmail.com');
            $passwordHash = self::getAppSetting('admin_password_hash');

            if (empty($passwordHash)) {
                return response()->json([
                    'success' => false,
                    'code' => 'NEEDS_SETUP',
                    'error' => 'প্রথমবার নতুন পাসওয়ার্ড সেট করুন।',
                ], 400);
            }

            $email = strtolower(trim((string) $request->input('email', '')));
            $password = (string) $request->input('password', '');

            if (!$email || !$password) {
                return response()->json(['success' => false, 'error' => 'ইমেইল এবং পাসওয়ার্ড দিন।'], 400);
            }

            // Owner login verification (supports standard PHP password_verify or Laravel Hash::check)
            if ($email === strtolower(trim($ownerEmail)) && (password_verify($password, $passwordHash) || Hash::check($password, $passwordHash))) {
                RateLimiter::clear($throttleKey);
                $displayName = (string) self::getAppSetting('admin_display_name', 'Samiul Biswas');
                $token = self::issueAuthToken($email, $displayName, 'owner');

                return response()->json([
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'uid' => 'cpanel-' . md5($email),
                        'email' => $email,
                        'displayName' => $displayName,
                        'role' => 'owner',
                    ],
                ]);
            }

            // Team member login check
            $rows = DB::table('sync_collections')
                ->where('collection_name', 'team_members')
                ->get(['data_json']);

            foreach ($rows as $row) {
                $member = json_decode((string) $row->data_json, true);
                if (
                    is_array($member) &&
                    !empty($member['email']) &&
                    strtolower(trim((string) $member['email'])) === $email &&
                    ($member['status'] ?? 'active') === 'active'
                ) {
                    $memberPass = (string) ($member['password'] ?? '');
                    $validPass = $memberPass !== ''
                        ? ($password === $memberPass || password_verify($password, $memberPass))
                        : password_verify($password, $passwordHash);

                    if ($validPass) {
                        RateLimiter::clear($throttleKey);
                        $displayName = (string) ($member['name'] ?? $email);
                        $role = (string) ($member['role'] ?? 'staff');
                        $token = self::issueAuthToken($email, $displayName, $role);

                        return response()->json([
                            'success' => true,
                            'token' => $token,
                            'user' => [
                                'uid' => 'cpanel-' . md5($email),
                                'email' => $email,
                                'displayName' => $displayName,
                                'role' => $role,
                            ],
                        ]);
                    }
                }
            }

            RateLimiter::hit($throttleKey, 60);
            return response()->json(['success' => false, 'error' => 'ইমেইল বা পাসওয়ার্ড ভুল হয়েছে।'], 401);
        } catch (Throwable $e) {
            $errDetail = config('app.debug') ? ': ' . $e->getMessage() : '';
            return response()->json(['success' => false, 'error' => 'সার্ভার প্রক্রিয়াকরণে সমস্যা হয়েছে' . $errDetail], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = self::getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'error' => 'অনুগ্রহ করে আবার লগইন করুন।',
                ], 401);
            }

            if (($user['role'] ?? '') !== 'owner') {
                return response()->json([
                    'success' => false,
                    'error' => 'শুধুমাত্র অ্যাডমিন পাসওয়ার্ড পরিবর্তন করতে পারবেন।',
                ], 403);
            }

            $newPassword = (string) $request->input('newPassword', '');
            if (strlen($newPassword) < 4) {
                return response()->json([
                    'success' => false,
                    'error' => 'কমপক্ষে ৪ অক্ষরের পাসওয়ার্ড দিন।',
                ], 400);
            }

            self::setAppSetting('admin_password_hash', password_hash($newPassword, PASSWORD_DEFAULT));

            return response()->json([
                'success' => true,
                'message' => 'পাসওয়ার্ড সফলভাবে আপডেট হয়েছে!',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
