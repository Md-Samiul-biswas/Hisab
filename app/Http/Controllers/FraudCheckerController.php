<?php

namespace App\Http\Controllers;

use Azmolla\FraudCheckerBdCourier\Helpers\CourierDataValidator;
use Azmolla\FraudCheckerBdCourier\Services\CarrybeeService;
use Azmolla\FraudCheckerBdCourier\Services\PaperflyService;
use Azmolla\FraudCheckerBdCourier\Services\PathaoService;
use Azmolla\FraudCheckerBdCourier\Services\RedxService;
use Azmolla\FraudCheckerBdCourier\Services\SteadfastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FraudCheckerController extends Controller
{
    private function cleanPhone(string $phone): string
    {
        $cleaned = (string) preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '880')) {
            $cleaned = substr($cleaned, 2);
        } elseif (str_starts_with($cleaned, '88')) {
            $cleaned = substr($cleaned, 2);
        }
        return $cleaned;
    }

    public function check(Request $request): JsonResponse
    {
        $rawPhone = (string) ($request->input('phone') ?? $request->input('mobile') ?? '');
        $phone = $this->cleanPhone($rawPhone);

        if (!preg_match('/^01[3-9]\d{8}$/', $phone)) {
            return response()->json([
                'success' => false,
                'error' => 'সঠিক বাংলাদেশি ১১ ডিজিটের মোবাইল নম্বর দিন (যেমন: 017XXXXXXXX)।',
            ], 422);
        }

        $results = [
            'steadfast' => null,
            'pathao' => null,
            'redx' => null,
            'paperfly' => null,
            'carrybee' => null,
        ];

        // 1. Check Pathao
        $results['pathao'] = $this->checkPathao($phone);

        // 2. Check Steadfast
        $results['steadfast'] = $this->checkSteadfast($phone);

        // 3. Check RedX
        $results['redx'] = $this->checkRedx($phone);

        // 4. Check Paperfly
        $results['paperfly'] = $this->checkPaperfly($phone);

        // 5. Check Carrybee
        $results['carrybee'] = $this->checkCarrybee($phone);

        // Compute Aggregate
        $totalSuccess = 0;
        $totalCancel = 0;

        foreach ($results as $courier => $stats) {
            if ($stats && empty($stats['error'])) {
                if (isset($stats['success']) && is_numeric($stats['success'])) {
                    $totalSuccess += (int) $stats['success'];
                }
                if (isset($stats['cancel']) && is_numeric($stats['cancel'])) {
                    $totalCancel += (int) $stats['cancel'];
                }
            }
        }

        $totalDeliveries = $totalSuccess + $totalCancel;
        $successRatio = $totalDeliveries > 0 ? round(($totalSuccess / $totalDeliveries) * 100, 1) : 0;
        $cancelRatio = $totalDeliveries > 0 ? round(($totalCancel / $totalDeliveries) * 100, 1) : 0;

        // Risk Assessment
        $risk = $this->assessRisk($totalDeliveries, $successRatio, $cancelRatio);

        return response()->json([
            'success' => true,
            'phone' => $phone,
            'aggregate' => [
                'total_deliveries' => $totalDeliveries,
                'total_success' => $totalSuccess,
                'total_cancel' => $totalCancel,
                'success_ratio' => $successRatio,
                'cancel_ratio' => $cancelRatio,
            ],
            'couriers' => $results,
            'risk' => $risk,
        ]);
    }

    private function checkPathao(string $phone): array
    {
        $user = trim((string) (config('fraud-checker-bd-courier.pathao.user') ?: env('PATHAO_USER') ?: env('PATHAO_USERNAME') ?: 'budgetchoicebd@gmail.com'));
        $pass = trim((string) (config('fraud-checker-bd-courier.pathao.password') ?: env('PATHAO_PASSWORD') ?: 'Tabiasami3@'));

        try {
            $token = (string) AppAuthController::getAppSetting('pathao_merchant_portal_token', '');
            $tokenExpiry = (int) AppAuthController::getAppSetting('pathao_merchant_portal_expiry', '0');

            if (!$token || time() >= ($tokenExpiry - 300)) {
                $loginRes = Http::timeout(12)->acceptJson()->post('https://merchant.pathao.com/api/v1/login', [
                    'username' => $user,
                    'password' => $pass,
                ]);

                if ($loginRes->successful()) {
                    $token = (string) ($loginRes->json('access_token') ?? '');
                    $expiresIn = (int) ($loginRes->json('expires_in') ?? 86400 * 30);
                    if ($token) {
                        AppAuthController::setAppSetting('pathao_merchant_portal_token', $token);
                        AppAuthController::setAppSetting('pathao_merchant_portal_expiry', (string) (time() + $expiresIn));
                    }
                } else {
                    Log::error('Pathao Merchant Login Failed: ' . $loginRes->body());
                }
            }

            if ($token) {
                $statRes = Http::timeout(12)->acceptJson()->withToken($token)->post('https://merchant.pathao.com/api/v1/user/success', [
                    'phone' => $phone,
                ]);

                if ($statRes->successful()) {
                    $data = $statRes->json('data') ?? [];
                    $cust = $data['customer'] ?? [];
                    $succ = (int) ($cust['successful_delivery'] ?? 0);
                    $tot = (int) ($cust['total_delivery'] ?? 0);
                    $canc = max(0, $tot - $succ);
                    $rating = (string) ($data['customer_rating'] ?? '');

                    return [
                        'success' => $succ,
                        'cancel' => $canc,
                        'total' => $tot,
                        'success_ratio' => $tot > 0 ? round(($succ / $tot) * 100, 1) : 0,
                        'rating' => $rating,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('Pathao Fraud Check Error: ' . $e->getMessage());
            return ['error' => 'Pathao API: ' . $e->getMessage()];
        }

        return ['total' => 0, 'success' => 0, 'cancel' => 0, 'success_ratio' => 0, 'status' => 'No prior records found'];
    }

    private function checkSteadfast(string $phone): array
    {
        try {
            if (config('fraud-checker-bd-courier.steadfast.user') && config('fraud-checker-bd-courier.steadfast.password')) {
                $service = new SteadfastService();
                return $service->getDeliveryStats($phone);
            }
        } catch (Throwable $e) {
            return ['error' => 'Steadfast: ' . $e->getMessage()];
        }

        return ['status' => 'not_configured', 'message' => 'Steadfast একাউন্ট কনফিগার করা নেই'];
    }

    private function checkRedx(string $phone): array
    {
        try {
            if (config('fraud-checker-bd-courier.redx.phone') && config('fraud-checker-bd-courier.redx.password')) {
                $service = new RedxService();
                return $service->getDeliveryStats($phone);
            }
        } catch (Throwable $e) {
            return ['error' => 'RedX: ' . $e->getMessage()];
        }

        return ['status' => 'not_configured', 'message' => 'RedX একাউন্ট কনফিগার করা নেই'];
    }

    private function checkPaperfly(string $phone): array
    {
        try {
            if (config('fraud-checker-bd-courier.paperfly.user') && config('fraud-checker-bd-courier.paperfly.password')) {
                $service = new PaperflyService();
                return $service->getDeliveryStats($phone);
            }
        } catch (Throwable $e) {
            return ['error' => 'Paperfly: ' . $e->getMessage()];
        }

        return ['status' => 'not_configured', 'message' => 'Paperfly একাউন্ট কনফিগার করা নেই'];
    }

    private function checkCarrybee(string $phone): array
    {
        try {
            if (config('fraud-checker-bd-courier.carrybee.phone') && config('fraud-checker-bd-courier.carrybee.password')) {
                $service = new CarrybeeService();
                return $service->getDeliveryStats($phone);
            }
        } catch (Throwable $e) {
            return ['error' => 'Carrybee: ' . $e->getMessage()];
        }

        return ['status' => 'not_configured', 'message' => 'Carrybee একাউন্ট কনফিগার করা নেই'];
    }

    private function assessRisk(int $totalDeliveries, float $successRatio, float $cancelRatio): array
    {
        if ($totalDeliveries === 0) {
            return [
                'level' => 'new',
                'title' => 'নতুন ক্রেতা (New Customer)',
                'badge' => 'bg-blue-100 text-blue-800 border-blue-200',
                'color' => 'blue',
                'recommendation' => 'কুরিয়ার নেটওয়ার্কে এই নম্বরে পূর্বের ডেলিভারি হিস্টোরি পাওয়া যায়নি। অর্ডার কনফার্ম করার জন্য ফোন কলে কথা বলে কনফার্মেশন নিন।',
            ];
        }

        if ($cancelRatio >= 40 || $successRatio < 60) {
            return [
                'level' => 'high',
                'title' => 'উচ্চ ঝুঁকিপূর্ণ (High Return Risk)',
                'badge' => 'bg-rose-100 text-rose-800 border-rose-300',
                'color' => 'rose',
                'recommendation' => "সতর্কতা! এই ক্রেতার ক্যানসেলেশন/রিটার্ন রেট {$cancelRatio}%। পার্সেল পাঠানোর আগে ডেলিভারি চার্জ অগ্রিম নিন অথবা যাচাই ছাড়া পাঠাবেন না।",
            ];
        }

        if ($cancelRatio >= 20 || $successRatio < 80) {
            return [
                'level' => 'medium',
                'title' => 'মধ্যম ঝুঁকি (Moderate Risk)',
                'badge' => 'bg-amber-100 text-amber-800 border-amber-300',
                'color' => 'amber',
                'recommendation' => "এই ক্রেতার সফল ডেলিভারি রেট {$successRatio}%। পার্সেল বুকিং করার আগে কাস্টমারকে কল দিয়ে ঠিকানা ও পার্সেল গ্রহণের নিশ্চয়তা নিন।",
            ];
        }

        return [
            'level' => 'safe',
            'title' => 'বিশ্বস্ত ও নিরাপদ ক্রেতা (Trusted Buyer)',
            'badge' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'color' => 'emerald',
            'recommendation' => "চমৎকার রেকর্ড! কুরিয়ার নেটওয়ার্কে এই ক্রেতার সফল ডেলিভারি রেট {$successRatio}% ({$totalDeliveries}টির মধ্যে)। নির্দ্বিধায় ক্যাশ-অন-ডেলিভারিতে পার্সেল পাঠাতে পারেন।",
        ];
    }
}
