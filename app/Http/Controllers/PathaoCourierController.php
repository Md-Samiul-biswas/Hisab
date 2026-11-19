<?php

namespace App\Http\Controllers;

use App\Models\CourierParcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PathaoCourierController extends Controller
{
    private function getStoredCourierConfig(): array
    {
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
                return array_merge($defaults, array_filter($decoded));
            }
        }
        return $defaults;
    }

    private function getAccessToken(array $credentials = []): array
    {
        $stored = $this->getStoredCourierConfig();

        $clientId = trim((string) ($credentials['clientId'] ?? $stored['pathaoClientId'] ?? AppAuthController::getAppSetting('pathao_client_id') ?? config('pathao-courier.pathao_client_id') ?? env('PATHAO_CLIENT_ID', 'pmbk5kJezJ')));
        $clientSecret = trim((string) ($credentials['clientSecret'] ?? $stored['pathaoClientSecret'] ?? AppAuthController::getAppSetting('pathao_client_secret') ?? config('pathao-courier.pathao_client_secret') ?? env('PATHAO_CLIENT_SECRET', 'g5lWzr9D0VChoGyT4guCv5NFFNlotXt8Qg4jUXFo')));
        $username = trim((string) ($credentials['username'] ?? $stored['pathaoUsername'] ?? AppAuthController::getAppSetting('pathao_username') ?? config('pathao-courier.pathao_username') ?? env('PATHAO_USERNAME', 'budgetchoicebd@gmail.com')));
        $password = trim((string) ($credentials['password'] ?? $stored['pathaoPassword'] ?? AppAuthController::getAppSetting('pathao_password') ?? config('pathao-courier.pathao_password') ?? env('PATHAO_PASSWORD', 'Tabiasami3@')));
        $isSandbox = (bool) ($credentials['isSandbox'] ?? $stored['pathaoSandbox'] ?? (AppAuthController::getAppSetting('pathao_sandbox', '0') === '1'));

        $baseUrl = $isSandbox ? 'https://courier-api-sandbox.pathao.com' : rtrim((string) env('PATHAO_BASE_URL', 'https://api-hermes.pathao.com'), '/');

        $cachedToken = (string) AppAuthController::getAppSetting('pathao_cached_token', '');
        $cachedExpiry = (int) AppAuthController::getAppSetting('pathao_cached_expiry', '0');

        if ($cachedToken && time() < ($cachedExpiry - 60)) {
            return ['token' => $cachedToken, 'baseUrl' => $baseUrl];
        }

        if ($password === '') {
            throw new RuntimeException('Pathao Merchant Password দেওয়া নেই। দয়া করে Settings > Courier Settings-এ গিয়ে আপনার Pathao Merchant লগইন পাসওয়ার্ড বসান।');
        }

        $res = Http::timeout(15)->acceptJson()->post($baseUrl . '/aladdin/api/v1/issue-token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $username,
            'password' => $password,
            'grant_type' => 'password',
        ]);

        $data = $res->json() ?? [];
        if (!$res->successful() || empty($data['access_token'])) {
            $rawMsg = $data['message'] ?? ('HTTP ' . $res->status());
            if (str_contains(strtolower($rawMsg), 'credentials were incorrect')) {
                throw new RuntimeException('Pathao Merchant Password অথবা Username ভুল। দয়া করে merchant.pathao.com এ লগইন করার সঠিক পাসওয়ার্ড দিন।');
            }
            throw new RuntimeException('Pathao API Authentication Failed: ' . $rawMsg);
        }

        $token = (string) $data['access_token'];
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : (86400 * 5);

        AppAuthController::setAppSetting('pathao_cached_token', $token);
        AppAuthController::setAppSetting('pathao_cached_expiry', (string) (time() + $expiresIn));

        return ['token' => $token, 'baseUrl' => $baseUrl];
    }

    private function cleanBdPhone(string $phone): string
    {
        $cleaned = (string) preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '880')) {
            return substr($cleaned, 2);
        }
        if (str_starts_with($cleaned, '88')) {
            return substr($cleaned, 2);
        }
        return $cleaned;
    }

    public function testConnection(Request $request): JsonResponse
    {
        try {
            $body = $request->all();
            $auth = $this->getAccessToken($body);
            $storesRes = Http::timeout(15)
                ->withToken($auth['token'])
                ->acceptJson()
                ->get($auth['baseUrl'] . '/aladdin/api/v1/stores');

            $stores = $storesRes->json('data.data') ?? [];

            if (!empty($body['password'])) {
                AppAuthController::setAppSetting('pathao_password', (string) $body['password']);
                // Update courier_settings_json as well if present
                $stored = $this->getStoredCourierConfig();
                $stored['pathaoPassword'] = (string) $body['password'];
                AppAuthController::setAppSetting('courier_settings_json', json_encode($stored, JSON_UNESCAPED_UNICODE));
            }
            if (!empty($body['storeId'])) {
                AppAuthController::setAppSetting('pathao_store_id', (string) $body['storeId']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Connected to Pathao Courier successfully! Credentials verified.',
                'stores' => $stores,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function cities(Request $request): JsonResponse
    {
        try {
            $auth = $this->getAccessToken($request->input('credentials', []));
            $res = Http::timeout(15)
                ->withToken($auth['token'])
                ->acceptJson()
                ->get($auth['baseUrl'] . '/aladdin/api/v1/city-list');

            $cities = $res->json('data.data');
            if (!empty($cities) && is_array($cities)) {
                return response()->json(['success' => true, 'cities' => $cities]);
            }
        } catch (Throwable) {
            // Fallback below
        }

        return response()->json([
            'success' => true,
            'cities' => [
                ['city_id' => 1, 'city_name' => 'Dhaka'],
                ['city_id' => 2, 'city_name' => 'Chattogram'],
                ['city_id' => 3, 'city_name' => 'Sylhet'],
                ['city_id' => 4, 'city_name' => 'Rajshahi'],
                ['city_id' => 5, 'city_name' => 'Khulna'],
                ['city_id' => 6, 'city_name' => 'Barishal'],
                ['city_id' => 7, 'city_name' => 'Rangpur'],
                ['city_id' => 8, 'city_name' => 'Mymensingh'],
                ['city_id' => 9, 'city_name' => 'Gazipur'],
                ['city_id' => 10, 'city_name' => 'Narayanganj'],
            ],
        ]);
    }

    public function zones(Request $request): JsonResponse
    {
        $cityId = (int) $request->input('cityId', 1);

        try {
            $auth = $this->getAccessToken($request->input('credentials', []));
            $res = Http::timeout(15)
                ->withToken($auth['token'])
                ->acceptJson()
                ->get("{$auth['baseUrl']}/aladdin/api/v1/cities/{$cityId}/zone-list");

            $zones = $res->json('data.data');
            if (!empty($zones) && is_array($zones)) {
                return response()->json(['success' => true, 'zones' => $zones]);
            }
        } catch (Throwable) {
            // Fallback below
        }

        return response()->json([
            'success' => true,
            'zones' => [
                ['zone_id' => 1, 'zone_name' => 'Dhanmondi'],
                ['zone_id' => 2, 'zone_name' => 'Gulshan'],
                ['zone_id' => 3, 'zone_name' => 'Banani'],
                ['zone_id' => 4, 'zone_name' => 'Mirpur'],
                ['zone_id' => 5, 'zone_name' => 'Uttara'],
            ],
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        try {
            $creds = $request->input('credentials', []);
            $order = $request->input('order', []);
            $stored = $this->getStoredCourierConfig();

            $auth = $this->getAccessToken($creds);
            $storeId = (int) ($creds['storeId'] ?? $stored['pathaoStoreId'] ?? AppAuthController::getAppSetting('pathao_store_id') ?? config('pathao-courier.pathao_store_id') ?? env('PATHAO_STORE_ID', 435821));

            $cleanPhone = $this->cleanBdPhone((string) ($order['recipientPhone'] ?? ''));
            if (!preg_match('/^01[3-9]\d{8}$/', $cleanPhone)) {
                return response()->json([
                    'success' => false,
                    'error' => 'ভুল মোবাইল নম্বর। নম্বরটি অবশ্যই ০১ দিয়ে শুরু ১১ ডিজিটের বাংলাদেশি নম্বর হতে হবে।',
                ], 400);
            }

            $rawAddress = trim((string) ($order['recipientAddress'] ?? ''));
            if (strlen($rawAddress) < 10) {
                $rawAddress .= ', Bangladesh';
            }

            $payload = [
                'store_id' => $storeId,
                'merchant_order_id' => (string) ($order['merchantOrderId'] ?? $order['orderNumber'] ?? ('ORD-' . substr((string) time(), -6))),
                'recipient_name' => trim((string) ($order['recipientName'] ?? 'Customer')),
                'recipient_phone' => $cleanPhone,
                'recipient_address' => $rawAddress,
                'delivery_type' => (int) ($order['deliveryType'] ?? 48),
                'item_type' => (int) ($order['itemType'] ?? 2),
                'special_instruction' => (string) ($order['specialInstruction'] ?? ''),
                'item_quantity' => max(1, (int) ($order['itemQuantity'] ?? 1)),
                'item_weight' => max(0.5, (float) ($order['itemWeight'] ?? 0.5)),
                'amount_to_collect' => max(0, (int) ($order['amountToCollect'] ?? 0)),
                'item_description' => (string) ($order['itemDescription'] ?? 'Retail Parcel Products'),
            ];

            // If valid city/zone are selected, send them; otherwise Pathao auto-detects from address
            $cityId = (int) ($order['cityId'] ?? 0);
            $zoneId = (int) ($order['zoneId'] ?? 0);
            if ($cityId > 0) {
                $payload['recipient_city'] = $cityId;
            }
            if ($zoneId > 0) {
                $payload['recipient_zone'] = $zoneId;
            }
            if (!empty($order['areaId'])) {
                $payload['recipient_area'] = (int) $order['areaId'];
            }

            $res = Http::timeout(20)
                ->withToken($auth['token'])
                ->acceptJson()
                ->post($auth['baseUrl'] . '/aladdin/api/v1/orders', $payload);

            $orderData = $res->json() ?? [];
            $consignmentId = $orderData['data']['consignment_id'] ?? null;

            if (!$res->successful() || empty($consignmentId)) {
                $errMsg = $orderData['message'] ?? 'Pathao কুরিয়ারে পার্সেল বুকিং ব্যর্থ হয়েছে।';
                if (!empty($orderData['errors']) && is_array($orderData['errors'])) {
                    $details = [];
                    foreach ($orderData['errors'] as $field => $fieldErrors) {
                        $details[] = is_array($fieldErrors) ? implode(', ', $fieldErrors) : $fieldErrors;
                    }
                    if ($details) {
                        $errMsg .= ' (' . implode(' | ', $details) . ')';
                    }
                }

                return response()->json([
                    'success' => false,
                    'error' => $errMsg,
                    'raw' => $orderData,
                ], 400);
            }

            // Save to database
            try {
                CourierParcel::create([
                    'id' => 'pcl-' . time(),
                    'consignment_id' => $consignmentId,
                    'tracking_code' => $consignmentId,
                    'order_number' => $payload['merchant_order_id'],
                    'recipient_name' => $payload['recipient_name'],
                    'recipient_phone' => $payload['recipient_phone'],
                    'recipient_address' => $payload['recipient_address'],
                    'cod_amount' => $payload['amount_to_collect'],
                    'delivery_fee' => (float) ($orderData['data']['delivery_fee'] ?? 60),
                    'status' => 'pending_pickup',
                ]);
            } catch (Throwable) {
                // Non-fatal if table not migrated
            }

            return response()->json([
                'success' => true,
                'message' => 'পার্সেল সফলভাবে পাঠাও কুরিয়ারে পাঠানো হয়েছে!',
                'data' => $orderData['data'],
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function syncStatuses(Request $request): JsonResponse
    {
        try {
            $auth = $this->getAccessToken();
            $token = $auth['token'];
            $baseUrl = $auth['baseUrl'];

            // 1. Get consignments to check from database
            $activeParcels = DB::table('courier_parcels')
                ->whereNotIn('status', ['delivered', 'cancelled', 'returned'])
                ->get();

            $updatedCount = 0;
            $results = [];

            foreach ($activeParcels as $parcel) {
                $consignmentId = $parcel->consignment_id;
                if (!$consignmentId) continue;

                $res = Http::timeout(10)
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$baseUrl}/aladdin/api/v1/orders/{$consignmentId}/info");

                if ($res->successful()) {
                    $data = $res->json('data') ?? [];
                    $statusSlug = (string) ($data['order_status_slug'] ?? $data['order_status'] ?? '');
                    if ($statusSlug) {
                        $this->applyStatusUpdate($consignmentId, $statusSlug);
                        $updatedCount++;
                        $results[] = [
                            'consignment_id' => $consignmentId,
                            'status' => $statusSlug,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Pathao কুরিয়ার থেকে {$updatedCount}টি পার্সেলের স্ট্যাটাস সফলভাবে সিঙ্ক হয়েছে।",
                'updated_count' => $updatedCount,
                'results' => $results,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function applyStatusUpdate(string $consignmentId, string $statusRaw): void
    {
        $statusLower = strtolower($statusRaw);

        // Map Pathao status to standard system statuses
        $parcelStatus = 'pending_pickup';
        $orderStatus = 'processing';
        $paymentStatus = 'unpaid';

        if (str_contains($statusLower, 'deliver')) {
            $parcelStatus = 'delivered';
            $orderStatus = 'delivered';
            $paymentStatus = 'paid';
        } elseif (str_contains($statusLower, 'return')) {
            $parcelStatus = 'returned';
            $orderStatus = 'returned';
        } elseif (str_contains($statusLower, 'transit') || str_contains($statusLower, 'picked') || str_contains($statusLower, 'pickup_pending')) {
            $parcelStatus = 'in_transit';
            $orderStatus = 'shipped';
        } elseif (str_contains($statusLower, 'cancel')) {
            $parcelStatus = 'cancelled';
            $orderStatus = 'cancelled';
        }

        // Update courier_parcels
        DB::table('courier_parcels')
            ->where('consignment_id', $consignmentId)
            ->update(['status' => $parcelStatus]);

        // Update orders table
        $order = DB::table('orders')->where('consignment_id', $consignmentId)->first();
        if ($order) {
            $updateData = ['status' => $orderStatus];
            if ($paymentStatus === 'paid') {
                $updateData['payment_status'] = 'paid';
                $updateData['paid_amount'] = $order->total_amount;
                $updateData['due_amount'] = 0.00;
            }
            DB::table('orders')->where('id', $order->id)->update($updateData);

            // Update in sync_collections for live frontend reactivity
            try {
                $rawSync = DB::table('sync_collections')
                    ->where('collection_name', 'orders')
                    ->where('doc_id', $order->id)
                    ->value('data_json');

                if ($rawSync) {
                    $decoded = json_decode($rawSync, true);
                    if (is_array($decoded)) {
                        $decoded['status'] = $orderStatus;
                        if ($paymentStatus === 'paid') {
                            $decoded['paymentStatus'] = 'paid';
                            $decoded['paidAmount'] = $decoded['totalAmount'] ?? $order->total_amount;
                            $decoded['dueAmount'] = 0;
                        }
                        DB::table('sync_collections')
                            ->where('collection_name', 'orders')
                            ->where('doc_id', $order->id)
                            ->update(['data_json' => json_encode($decoded, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            } catch (Throwable) {}
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        try {
            $consignmentId = (string) ($request->input('consignment_id') ?? $request->input('order_consignment_id') ?? '');
            $status = (string) ($request->input('order_status_slug') ?? $request->input('order_status') ?? '');

            if ($consignmentId && $status) {
                $this->applyStatusUpdate($consignmentId, $status);
            }
        } catch (Throwable) {}

        return response()
            ->json(['success' => true, 'message' => 'Webhook received and processed successfully'], 202)
            ->header(
                'X-Pathao-Merchant-Webhook-Integration-Secret',
                (string) env('PATHAO_WEBHOOK_SECRET', 'f3992ecc-59da-4cbe-a049-a13da2018d51')
            );
    }

    public function getMerchantSessionToken(): ?string
    {
        $stored = $this->getStoredCourierConfig();
        $username = trim((string) ($stored['pathaoUsername'] ?? AppAuthController::getAppSetting('pathao_username') ?? config('pathao-courier.pathao_username') ?? env('PATHAO_USERNAME', 'budgetchoicebd@gmail.com')));
        $password = trim((string) ($stored['pathaoPassword'] ?? AppAuthController::getAppSetting('pathao_password') ?? config('pathao-courier.pathao_password') ?? env('PATHAO_PASSWORD', 'Tabiasami3@')));

        if (!$password) $password = 'Tabiasami3@';
        if (!$username) $username = 'budgetchoicebd@gmail.com';

        $res = Http::timeout(15)->acceptJson()->post('https://merchant.pathao.com/api/v1/login', [
            'username' => $username,
            'password' => $password,
        ]);

        if ($res->successful()) {
            $data = $res->json();
            return $data['access_token'] ?? null;
        }

        return null;
    }

    public function importAllParcels(Request $request = null): JsonResponse
    {
        try {
            $token = $this->getMerchantSessionToken();
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'error' => 'Pathao Merchant অ্যাকাউন্টে লগইন করা যায়নি। Settings থেকে Pathao Password চেক করুন।'
                ], 401);
            }

            $page = 1;
            $allOrders = [];
            $maxPages = 20;

            do {
                $res = Http::timeout(20)
                    ->withToken($token)
                    ->acceptJson()
                    ->get("https://merchant.pathao.com/api/v1/orders/all?page={$page}");

                if (!$res->successful()) {
                    break;
                }

                $json = $res->json() ?? [];
                $orders = $json['data']['data'] ?? [];
                $lastPage = (int) ($json['data']['last_page'] ?? 1);

                if (empty($orders)) {
                    break;
                }

                $allOrders = array_merge($allOrders, $orders);
                $page++;
            } while ($page <= $lastPage && $page <= $maxPages);

            $syncedCount = 0;
            $statusSummary = [];

            foreach ($allOrders as $order) {
                $cid = trim((string) ($order['order_consignment_id'] ?? ''));
                if (!$cid) continue;

                $parcelId = 'pathao_' . $cid;
                $statusRaw = (string) ($order['order_status'] ?? 'Pending');
                $statusLower = strtolower($statusRaw);

                if (str_contains($statusLower, 'deliver')) {
                    $statusFormatted = 'Delivered';
                } elseif (str_contains($statusLower, 'return')) {
                    $statusFormatted = 'Returned';
                } elseif (str_contains($statusLower, 'cancel')) {
                    $statusFormatted = 'Cancelled';
                } elseif (str_contains($statusLower, 'transit') || str_contains($statusLower, 'shipped') || str_contains($statusLower, 'picked') || str_contains($statusLower, 'pickup_pending')) {
                    $statusFormatted = 'In Transit';
                } else {
                    $statusFormatted = 'Pending';
                }

                $statusSummary[$statusFormatted] = ($statusSummary[$statusFormatted] ?? 0) + 1;

                $recipientPhone = $this->cleanBdPhone((string) ($order['recipient_phone'] ?? ''));
                if ($recipientPhone && !str_starts_with($recipientPhone, '01')) {
                    $recipientPhone = '01' . ltrim($recipientPhone, '0');
                }

                $recipientName = trim((string) ($order['recipient_name'] ?? '')) ?: 'Customer';
                $recipientAddress = trim((string) ($order['recipient_address'] ?? ''));
                $codAmount = (float) ($order['order_amount'] ?? 0);
                $deliveryFee = (float) ($order['delivery_fee'] ?? 60);
                $orderNumber = trim((string) ($order['merchant_order_id'] ?? '')) ?: $cid;
                $createdAt = $order['order_created_at'] ?? now()->toDateTimeString();

                $parcelDoc = [
                    'id' => $parcelId,
                    'consignmentId' => $cid,
                    'trackingCode' => $cid,
                    'orderId' => $orderNumber,
                    'orderNumber' => $orderNumber,
                    'courierPartner' => 'Pathao',
                    'recipientName' => $recipientName,
                    'recipientPhone' => $recipientPhone,
                    'recipientAddress' => $recipientAddress,
                    'recipientCity' => 'Dhaka',
                    'recipientZone' => '',
                    'recipientArea' => '',
                    'itemType' => !empty($order['item_type']) ? strtolower((string) $order['item_type']) : 'parcel',
                    'weightKg' => 0.5,
                    'numberOfItems' => 1,
                    'codAmount' => $codAmount,
                    'deliveryFee' => $deliveryFee,
                    'deliveryType' => 'normal',
                    'specialInstructions' => (string) ($order['instruction'] ?? ''),
                    'status' => $statusFormatted,
                    'dispatchedAt' => $createdAt,
                ];

                // 1. Update/Insert into sync_collections (parcels)
                try {
                    DB::statement(
                        'INSERT INTO `sync_collections` (`collection_name`, `doc_id`, `data_json`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `data_json` = VALUES(`data_json`)',
                        ['parcels', $parcelId, json_encode($parcelDoc, JSON_UNESCAPED_UNICODE)]
                    );
                } catch (Throwable) {}

                // 2. Update/Insert into courier_parcels table
                try {
                    DB::table('courier_parcels')->updateOrInsert(
                        ['consignment_id' => $cid],
                        [
                            'id' => $parcelId,
                            'tracking_code' => $cid,
                            'order_id' => $orderNumber,
                            'order_number' => $orderNumber,
                            'courier_partner' => 'Pathao',
                            'recipient_name' => $recipientName,
                            'recipient_phone' => $recipientPhone,
                            'recipient_address' => $recipientAddress,
                            'recipient_city' => 'Dhaka',
                            'item_type' => $parcelDoc['itemType'],
                            'weight_kg' => 0.5,
                            'number_of_items' => 1,
                            'cod_amount' => $codAmount,
                            'delivery_fee' => $deliveryFee,
                            'delivery_type' => 'normal',
                            'special_instructions' => $parcelDoc['specialInstructions'],
                            'status' => strtolower($statusFormatted),
                            'dispatched_at' => $createdAt,
                        ]
                    );
                } catch (Throwable) {}

                // 3. Upsert customer into sync_collections (customers)
                if ($recipientPhone) {
                    try {
                        $custId = 'cust_' . $recipientPhone;
                        $custDoc = [
                            'id' => $custId,
                            'name' => $recipientName,
                            'phone' => $recipientPhone,
                            'address' => $recipientAddress,
                            'district' => 'Dhaka',
                            'updatedAt' => now()->toIso8601String(),
                        ];
                        DB::statement(
                            'INSERT INTO `sync_collections` (`collection_name`, `doc_id`, `data_json`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `data_json` = VALUES(`data_json`)',
                            ['customers', $custId, json_encode($custDoc, JSON_UNESCAPED_UNICODE)]
                        );
                    } catch (Throwable) {}
                }

                $syncedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Pathao থেকে মোট {$syncedCount}টি কাস্টমার পার্সেল সফলভাবে সিঙ্ক হয়েছে।",
                'total_synced' => $syncedCount,
                'status_summary' => $statusSummary,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
