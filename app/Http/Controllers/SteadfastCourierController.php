<?php

namespace App\Http\Controllers;

use App\Models\CourierParcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class SteadfastCourierController extends Controller
{
    public function createOrder(Request $request): JsonResponse
    {
        try {
            $storedRaw = AppAuthController::getAppSetting('courier_settings_json');
            $stored = $storedRaw ? (json_decode($storedRaw, true) ?? []) : [];

            $apiKey = trim((string) ($request->input('apiKey') ?: ($stored['steadfastApiKey'] ?? env('STEADFAST_API_KEY', ''))));
            $secretKey = trim((string) ($request->input('secretKey') ?: ($stored['steadfastSecretKey'] ?? env('STEADFAST_SECRET_KEY', ''))));
            $order = $request->input('order', []);

            if ($apiKey === '' || $secretKey === '') {
                return response()->json([
                    'success' => false,
                    'error' => 'Steadfast API Key and Secret Key are required.',
                ], 400);
            }

            $phone = (string) preg_replace('/[^0-9]/', '', (string) ($order['recipientPhone'] ?? ''));
            if (str_starts_with($phone, '880')) {
                $phone = substr($phone, 2);
            } elseif (str_starts_with($phone, '88')) {
                $phone = substr($phone, 2);
            }

            $payload = [
                'invoice' => (string) ($order['merchantOrderId'] ?? ('ORD-' . substr((string) time(), -6))),
                'recipient_name' => (string) ($order['recipientName'] ?? 'Customer'),
                'recipient_phone' => $phone,
                'recipient_address' => (string) ($order['recipientAddress'] ?? ''),
                'cod_amount' => (float) ($order['amountToCollect'] ?? 0),
                'note' => (string) ($order['specialInstruction'] ?? ''),
            ];

            $res = Http::timeout(15)
                ->withHeaders([
                    'Api-Key' => $apiKey,
                    'Secret-Key' => $secretKey,
                ])
                ->acceptJson()
                ->post('https://portal.steadfast.com.bd/api/v1/create_order', $payload);

            $sfData = $res->json() ?? [];
            if (!$res->successful() || (int) ($sfData['status'] ?? 0) !== 200) {
                return response()->json([
                    'success' => false,
                    'error' => $sfData['message'] ?? 'Steadfast order booking failed.',
                    'raw' => $sfData,
                ], 400);
            }

            $consignment = $sfData['consignment'] ?? [];
            $consignmentId = (string) ($consignment['consignment_id'] ?? $consignment['tracking_code'] ?? '');
            $trackingCode = (string) ($consignment['tracking_code'] ?? '');

            try {
                CourierParcel::create([
                    'id' => 'pcl-' . time(),
                    'consignment_id' => $consignmentId,
                    'tracking_code' => $trackingCode,
                    'order_number' => $payload['invoice'],
                    'recipient_name' => $payload['recipient_name'],
                    'recipient_phone' => $payload['recipient_phone'],
                    'recipient_address' => $payload['recipient_address'],
                    'cod_amount' => $payload['cod_amount'],
                    'delivery_fee' => 120,
                    'status' => 'pending_pickup',
                ]);
            } catch (Throwable) {
                // Ignore if optional
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully on Steadfast Courier!',
                'data' => [
                    'consignment_id' => $consignmentId,
                    'tracking_code' => $trackingCode,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage() ?: 'Failed to dispatch order with Steadfast Courier.',
            ], 500);
        }
    }
}
