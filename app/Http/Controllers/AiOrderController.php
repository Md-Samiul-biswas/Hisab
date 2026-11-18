<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiOrderController extends Controller
{
    private function getApiKey(Request $request): string
    {
        return trim((string) (
            $request->header('x-gemini-api-key')
            ?: $request->input('apiKey')
            ?: env('GEMINI_API_KEY', '')
        ));
    }

    private function convertBnDigits(string $text): string
    {
        return strtr($text, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
    }

    private function parseOrderFallback(string $rawText, array $existingProducts = []): array
    {
        $normalized = $this->convertBnDigits($rawText);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized))));

        $customerName = '';
        $customerPhone = '';
        $zilla = '';
        $thana = '';
        $unionName = '';
        $gram = '';
        $deliveryCost = 0;
        $discount = 0;
        $totalAmount = 0;
        $subTotal = 0;
        $advancePayment = 0;
        $items = [];

        foreach ($lines as $line) {
            if (preg_match('/(?:নাম|name)\s*[:：\-]\s*([^,\n\r]+)/iu', $line, $m)) {
                $customerName = trim(str_replace(['*', '_', '#'], '', $m[1]));
            }
            if (!$customerPhone && preg_match('/(?:\+?88)?01[3-9][\d\- ]{8,11}/', $line, $m)) {
                $p = (string) preg_replace('/[^0-9]/', '', $m[0]);
                if (str_starts_with($p, '880')) {
                    $p = substr($p, 2);
                } elseif (str_starts_with($p, '88')) {
                    $p = substr($p, 2);
                }
                $customerPhone = $p;
            }
            if (preg_match('/জেলা[ঃ:]\s*([^,\n\r?।]+)/iu', $line, $m)) {
                $zilla = trim($m[1]);
            }
            if (preg_match('/থানা[ঃ:]\s*([^,\n\r?।]+)/iu', $line, $m)) {
                $thana = trim($m[1]);
            }
            if (preg_match('/(?:ইউনিয়ন|ইউনিয়ন)\s*[ঃ:]?\s*([^,\n\r?।]+)/iu', $line, $m)) {
                $unionName = trim($m[1]);
            }
            if (preg_match('/গ্রাম[ঃ:]\s*([^,\n\r?।]+)/iu', $line, $m)) {
                $gram = trim($m[1]);
            }
            if (preg_match('/(?:ডেলিভারি\s*চার্জ|delivery\s*charge)[^0-9]*?(\d[\d,]*)/iu', $line, $m)) {
                $deliveryCost = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:ডিসকাউন্ট|ছাড়|discount)[^0-9]*?(\d[\d,]*)/iu', $line, $m)) {
                $discount = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:সর্বমোট\s*=|subtotal)[^0-9]*?(\d[\d,]*)/iu', $line, $m)) {
                $subTotal = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:অগ্রিম\s*পেমেন্ট|advance)[^0-9]*?(\d[\d,]*)/iu', $line, $m)) {
                $advancePayment = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:total|পরিশোধযোগ্য|সর্বমোট\s*বিল|মোট\s*বিল)[^0-9]*?(\d[\d,]*)/iu', $line, $m)) {
                $totalAmount = (int) str_replace(',', '', $m[1]);
            }
            if (preg_match('/(?:^\d+\.\s*)?(.+?)\s*\[(\d+)[xX]\]\s*(?:[\-=:৳\s]*)(\d[\d,]*)/u', $line, $m)) {
                $qty = max(1, (int) $m[2]);
                $lineTotal = (int) str_replace(',', '', $m[3]);
                $items[] = [
                    'productName' => trim($m[1]),
                    'quantity' => $qty,
                    'unitPrice' => (int) round($lineTotal / $qty),
                    'unitCost' => (int) round(($lineTotal / $qty) * 0.6),
                ];
            }
        }

        $addrParts = array_filter([
            $gram ? "গ্রাম: {$gram}" : '',
            $unionName ? "ইউনিয়ন: {$unionName}" : '',
            $thana ? "থানা: {$thana}" : '',
            $zilla ? "জেলা: {$zilla}" : '',
        ]);

        if (empty($items)) {
            $items[] = [
                'productName' => 'Custom Combo Item',
                'quantity' => 1,
                'unitPrice' => $totalAmount ?: 500,
                'unitCost' => 300,
            ];
        }

        return [
            'customerName' => $customerName ?: 'Customer',
            'customerPhone' => $customerPhone ?: '01700000000',
            'customerAddress' => $addrParts ? implode(', ', $addrParts) : 'Bangladesh',
            'dispatchDate' => '',
            'items' => $items,
            'deliveryCost' => $deliveryCost,
            'discount' => $discount,
            'subTotal' => $subTotal ?: max(0, $totalAmount - $deliveryCost),
            'totalAmount' => $totalAmount ?: ($subTotal + $deliveryCost - $discount),
            'advancePayment' => $advancePayment,
            'notes' => 'Parsed by Laravel order engine',
        ];
    }

    public function extractOrder(Request $request): JsonResponse
    {
        $imageBase64 = (string) $request->input('imageBase64', '');
        $mimeType = (string) $request->input('mimeType', 'image/jpeg');
        $textPrompt = (string) $request->input('textPrompt', '');
        $existingProducts = $request->input('existingProducts', []);
        $cleanBase64 = $imageBase64 ? trim((string) preg_replace('/^data:[^;]+;base64,/', '', $imageBase64)) : '';

        if (!$cleanBase64 && !$textPrompt) {
            return response()->json(['success' => false, 'error' => 'Image data or text description is required'], 400);
        }

        $apiKey = $this->getApiKey($request);
        $parsedData = null;
        $isAiParsed = false;

        if ($apiKey !== '') {
            try {
                $parts = [];
                if ($cleanBase64 !== '') {
                    $parts[] = [
                        'inlineData' => [
                            'data' => $cleanBase64,
                            'mimeType' => $mimeType,
                        ],
                    ];
                }
                $parts[] = [
                    'text' => "Extract Bangladeshi customer order details as strict JSON with keys: customerName, customerPhone, customerAddress, dispatchDate, items (array of {matchedProductId, productName, quantity, unitPrice, unitCost}), deliveryCost, discount, subTotal, totalAmount, advancePayment, notes. " . ($textPrompt ? "Text: {$textPrompt}" : ''),
                ];

                $res = Http::timeout(25)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                    [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ]
                );

                $text = $res->json('candidates.0.content.parts.0.text');
                if ($text) {
                    $decoded = json_decode((string) $text, true);
                    if (is_array($decoded)) {
                        $parsedData = $decoded;
                        $isAiParsed = true;
                    }
                }
            } catch (Throwable) {
                // Fallback below
            }
        }

        if (!$parsedData) {
            $parsedData = $this->parseOrderFallback($textPrompt, is_array($existingProducts) ? $existingProducts : []);
        }

        return response()->json([
            'success' => true,
            'data' => $parsedData,
            'isAiParsed' => $isAiParsed,
            'engine' => $isAiParsed ? 'gemini' : 'laravel-fallback',
        ]);
    }

    public function extractExpenses(Request $request): JsonResponse
    {
        $imageBase64 = (string) $request->input('imageBase64', '');
        $mimeType = (string) $request->input('mimeType', 'image/jpeg');
        $textPrompt = (string) $request->input('textPrompt', '');
        $defaultCategory = (string) $request->input('defaultCategory', 'Product Procurement');
        $cleanBase64 = $imageBase64 ? trim((string) preg_replace('/^data:[^;]+;base64,/', '', $imageBase64)) : '';

        if (!$cleanBase64 && !$textPrompt) {
            return response()->json(['success' => false, 'error' => 'Receipt image or memo text is required'], 400);
        }

        $apiKey = $this->getApiKey($request);
        if ($apiKey !== '') {
            try {
                $parts = [];
                if ($cleanBase64 !== '') {
                    $parts[] = [
                        'inlineData' => [
                            'data' => $cleanBase64,
                            'mimeType' => $mimeType,
                        ],
                    ];
                }
                $parts[] = [
                    'text' => "Extract Bangladeshi business expense items as JSON with keys: vendorName, invoiceNumber, date (YYYY-MM-DD), totalReceiptAmount, expenses (array of {title, quantity, unitCost, totalAmount, expenseType ('product'|'operating'), category, notes}). " . ($textPrompt ? "Note: {$textPrompt}" : ''),
                ];

                $res = Http::timeout(25)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                    [
                        'contents' => [['parts' => $parts]],
                        'generationConfig' => ['responseMimeType' => 'application/json'],
                    ]
                );

                $text = $res->json('candidates.0.content.parts.0.text');
                if ($text) {
                    $decoded = json_decode((string) $text, true);
                    if (is_array($decoded) && !empty($decoded['expenses'])) {
                        return response()->json([
                            'success' => true,
                            'data' => $decoded,
                            'isAiParsed' => true,
                            'engine' => 'gemini',
                        ]);
                    }
                }
            } catch (Throwable) {
                // Fallback below
            }
        }

        $expenses = [];
        $normalized = $this->convertBnDigits($textPrompt);
        foreach (array_filter(array_map('trim', explode("\n", $normalized))) as $line) {
            if (preg_match('/^(.+?)\s+(\d[\d,]*)\s*(?:টাকা|tk|\/-)?$/iu', $line, $m)) {
                $amount = (int) str_replace(',', '', $m[2]);
                if ($amount > 0) {
                    $expenses[] = [
                        'title' => trim($m[1]),
                        'quantity' => 1,
                        'unitCost' => $amount,
                        'totalAmount' => $amount,
                        'expenseType' => 'operating',
                        'category' => $defaultCategory ?: 'Other Expenses',
                        'notes' => 'Parsed from expense note',
                    ];
                }
            }
        }

        if (empty($expenses)) {
            $expenses[] = [
                'title' => 'Receipt Expense',
                'quantity' => 1,
                'unitCost' => 100,
                'totalAmount' => 100,
                'expenseType' => 'product',
                'category' => $defaultCategory ?: 'Product Procurement',
                'notes' => 'Fallback expense item',
            ];
        }

        $total = array_sum(array_column($expenses, 'totalAmount'));

        return response()->json([
            'success' => true,
            'data' => [
                'vendorName' => 'Vendor / Supplier',
                'invoiceNumber' => 'INV-' . substr((string) time(), -4),
                'date' => date('Y-m-d'),
                'totalReceiptAmount' => $total,
                'expenses' => $expenses,
            ],
            'isAiParsed' => false,
            'engine' => 'laravel-fallback',
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $messages = $request->input('messages', []);
        $systemInstruction = (string) $request->input(
            'systemInstruction',
            'You are "বাজেট চয়েস বিজনেস অ্যাসিস্ট্যান্ট", a helpful Bangladeshi e-commerce business consultant. Reply concisely in clear Bengali.'
        );

        if (!is_array($messages) || empty($messages)) {
            return response()->json(['success' => false, 'error' => 'Messages array is required'], 400);
        }

        $apiKey = $this->getApiKey($request);
        if ($apiKey === '') {
            return response()->json([
                'success' => false,
                'error' => 'Gemini API Key (.env -> GEMINI_API_KEY) কনফিগার করা নেই।',
            ], 400);
        }

        try {
            $contents = array_map(function ($m) {
                return [
                    'role' => (($m['role'] ?? 'user') === 'assistant' || ($m['role'] ?? 'user') === 'model') ? 'model' : 'user',
                    'parts' => [['text' => (string) ($m['content'] ?? '')]],
                ];
            }, $messages);

            $res = Http::timeout(25)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}",
                [
                    'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
                    'contents' => $contents,
                ]
            );

            $reply = $res->json('candidates.0.content.parts.0.text');
            if (!$reply) {
                return response()->json([
                    'success' => false,
                    'error' => $res->json('error.message') ?? 'Chat generation failed',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'model' => 'gemini-2.5-flash',
                'reply' => $reply,
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
