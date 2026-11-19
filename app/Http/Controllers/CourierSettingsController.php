<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CourierSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = AppAuthController::getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'error' => 'কুরিয়ার সেটিংস দেখার জন্য লগইন করা আবশ্যক।',
            ], 401);
        }

        try {
            AppAuthController::ensureCoreTables();

            $defaults = [
                'defaultCourier' => 'Pathao',
                'pathaoStoreId' => (string) (env('PATHAO_STORE_ID') ?: '435821'),
                'pathaoClientId' => (string) (env('PATHAO_CLIENT_ID') ?: 'pmbk5kJezJ'),
                'pathaoClientSecret' => (string) (env('PATHAO_CLIENT_SECRET') ?: 'g5lWzr9D0VChoGyT4guCv5NFFNlotXt8Qg4jUXFo'),
                'pathaoUsername' => (string) (env('PATHAO_USERNAME') ?: 'budgetchoicebd@gmail.com'),
                'pathaoPassword' => (string) (env('PATHAO_PASSWORD') ?: 'Tabiasami3@'),
                'pathaoSandbox' => false,
            ];

            $raw = AppAuthController::getAppSetting('courier_settings_json');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($defaults as $k => $v) {
                        if (empty($decoded[$k])) {
                            $decoded[$k] = $v;
                        }
                    }
                    return response()->json([
                        'success' => true,
                        'settings' => $decoded,
                        'data' => $decoded,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'settings' => $defaults,
                'data' => $defaults,
            ]);
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => 'কুরিয়ার সেটিংস লোড করা যায়নি।'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = AppAuthController::getAuthenticatedUser($request);
        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHORIZED',
                'error' => 'অননুমোদিত এক্সেস। অনুগ্রহ করে লগইন করুন।',
            ], 401);
        }

        // Only Owner can modify Courier API credentials
        if (($user['role'] ?? '') !== 'owner') {
            return response()->json([
                'success' => false,
                'code' => 'FORBIDDEN',
                'error' => 'শুধুমাত্র ওনার (Owner) কুরিয়ার সেটিংস পরিবর্তন করতে পারবেন।',
            ], 403);
        }

        try {
            AppAuthController::ensureCoreTables();
            $payload = $request->input('settings') ?? $request->all();
            AppAuthController::setAppSetting(
                'courier_settings_json',
                (string) json_encode($payload, JSON_UNESCAPED_UNICODE)
            );

            return response()->json([
                'success' => true,
                'message' => 'Courier API settings successfully saved on server.',
            ]);
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => 'সেটিংস সংরক্ষণ ব্যর্থ হয়েছে।'], 500);
        }
    }
}
