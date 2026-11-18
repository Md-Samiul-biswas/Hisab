<?php

namespace App\Http\Controllers;

use App\Models\SyncCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncController extends Controller
{
    private function cleanDocId(string $id): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_\-]/', '_', $id);
    }

    private function upsertDoc(string $col, string $docId, array $data): void
    {
        DB::statement(
            'INSERT INTO `sync_collections` (`collection_name`, `doc_id`, `data_json`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `data_json` = VALUES(`data_json`)',
            [$col, $docId, json_encode($data, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function handle(Request $request, ?string $routeAction = null): JsonResponse
    {
        try {
            $user = AppAuthController::getAuthenticatedUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'code' => 'UNAUTHORIZED',
                    'error' => 'অনুগ্রহ করে আবার লগইন করুন।',
                ], 401);
            }

            $action = (string) ($routeAction ?: $request->query('action') ?: $request->input('action', ''));

            if ($request->isMethod('get')) {
                if ($action === 'ping') {
                    return response()->json(['success' => true, 'time' => now()->toIso8601String()]);
                }
                return $this->pull();
            }

            if ($request->isMethod('post')) {
                return match ($action) {
                    'upsert', 'save_doc' => $this->upsert($request),
                    'batch' => $this->batch($request),
                    'delete' => $this->deleteDoc($request),
                    'clear' => $this->clearCollection($request),
                    'import_all' => $this->importAll($request),
                    default => response()->json(['success' => false, 'error' => 'Unsupported sync action'], 400),
                };
            }

            return response()->json(['success' => false, 'error' => 'Unsupported sync request'], 400);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function pull(): JsonResponse
    {
        $rows = SyncCollection::all(['collection_name', 'doc_id', 'data_json']);
        $collections = [];
        $settingsDoc = null;
        $courierSettingsDoc = null;

        foreach ($rows as $row) {
            $col = (string) $row->collection_name;
            $docId = (string) $row->doc_id;
            $parsed = json_decode((string) $row->data_json, true);
            if (!is_array($parsed)) {
                continue;
            }
            if ($col === 'settings' && $docId === 'global') {
                $settingsDoc = $parsed;
                continue;
            }
            if ($col === 'courier_settings' && $docId === 'global') {
                $courierSettingsDoc = $parsed;
                continue;
            }
            if (!isset($collections[$col])) {
                $collections[$col] = [];
            }
            if (!isset($parsed['id'])) {
                $parsed['id'] = $docId;
            }
            $collections[$col][] = $parsed;
        }

        return response()->json([
            'success' => true,
            'collections' => $collections,
            'settings' => $settingsDoc,
            'courierSettings' => $courierSettingsDoc,
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    private function upsert(Request $request): JsonResponse
    {
        $col = trim((string) $request->input('collection', ''));
        $docId = $this->cleanDocId((string) $request->input('id', 'global'));
        $data = $request->input('data');

        if (!$col || !is_array($data)) {
            return response()->json(['success' => false, 'error' => 'Collection and data are required'], 400);
        }

        $this->upsertDoc($col, $docId, $data);
        return response()->json(['success' => true]);
    }

    private function batch(Request $request): JsonResponse
    {
        $col = trim((string) $request->input('collection', ''));
        $items = $request->input('items', []);

        if (!$col || !is_array($items)) {
            return response()->json(['success' => false, 'error' => 'Collection and items array required'], 400);
        }

        DB::transaction(function () use ($col, $items) {
            foreach ($items as $item) {
                if (!is_array($item) || empty($item['id'])) {
                    continue;
                }
                $docId = $this->cleanDocId((string) $item['id']);
                $this->upsertDoc($col, $docId, $item);
            }
        });

        return response()->json(['success' => true, 'count' => count($items)]);
    }

    private function deleteDoc(Request $request): JsonResponse
    {
        $col = trim((string) $request->input('collection', ''));
        $docId = $this->cleanDocId((string) $request->input('id', ''));

        if (!$col || !$docId) {
            return response()->json(['success' => false, 'error' => 'Collection and id required'], 400);
        }

        DB::table('sync_collections')
            ->where('collection_name', $col)
            ->where('doc_id', $docId)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function clearCollection(Request $request): JsonResponse
    {
        $col = trim((string) $request->input('collection', ''));
        if (!$col) {
            return response()->json(['success' => false, 'error' => 'Collection required'], 400);
        }

        DB::table('sync_collections')->where('collection_name', $col)->delete();
        return response()->json(['success' => true]);
    }

    private function importAll(Request $request): JsonResponse
    {
        $data = $request->input('data', []);
        if (!is_array($data)) {
            return response()->json(['success' => false, 'error' => 'Invalid import payload'], 400);
        }

        $map = [
            'products' => 'products',
            'orders' => 'orders',
            'customers' => 'customers',
            'suppliers' => 'suppliers',
            'purchases' => 'purchases',
            'expenses' => 'expenses',
            'expenseCategories' => 'expense_categories',
            'expense_categories' => 'expense_categories',
            'accounts' => 'accounts',
            'transactions' => 'transactions',
            'stockLedger' => 'stock_ledger',
            'stock_ledger' => 'stock_ledger',
            'returns' => 'returns',
            'packages' => 'packages',
            'packagingMaterials' => 'packaging_materials',
            'packaging_materials' => 'packaging_materials',
            'parcels' => 'parcels',
            'teamMembers' => 'team_members',
            'team_members' => 'team_members',
            'assets' => 'assets',
        ];

        $totalSaved = 0;

        DB::transaction(function () use ($data, $map, &$totalSaved) {
            foreach ($map as $payloadKey => $colName) {
                if (!empty($data[$payloadKey]) && is_array($data[$payloadKey])) {
                    foreach ($data[$payloadKey] as $item) {
                        if (!is_array($item) || empty($item['id'])) {
                            continue;
                        }
                        $docId = $this->cleanDocId((string) $item['id']);
                        $this->upsertDoc($colName, $docId, $item);
                        $totalSaved++;
                    }
                }
            }

            if (!empty($data['settings']) && is_array($data['settings'])) {
                $this->upsertDoc('settings', 'global', $data['settings']);
                $totalSaved++;
            }
            if (!empty($data['courierSettings']) && is_array($data['courierSettings'])) {
                $this->upsertDoc('courier_settings', 'global', $data['courierSettings']);
                $totalSaved++;
            }
        });

        return response()->json(['success' => true, 'saved' => $totalSaved]);
    }
}
