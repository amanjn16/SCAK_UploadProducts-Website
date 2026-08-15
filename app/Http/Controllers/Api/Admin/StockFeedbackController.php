<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockSession;
use App\Models\StockSessionItem;
use App\Models\Supplier;
use App\Models\SupplierStockItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockFeedbackController extends Controller
{
    public function suppliers(): JsonResponse
    {
        return response()->json(['data' => Supplier::query()->withCount(['stockItems' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get()]);
    }

    public function start(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate(['count_date' => ['nullable', 'date']]);
        $date = $data['count_date'] ?? now()->toDateString();

        $session = DB::transaction(function () use ($supplier, $date, $request): StockSession {
            $session = StockSession::query()->firstOrCreate(
                ['supplier_id' => $supplier->id, 'count_date' => $date],
                ['status' => 'draft', 'created_by' => $request->user()?->id]
            );
            abort_if($session->status === 'submitted', 409, 'This stock session has already been submitted.');

            $existingIds = $session->items()->pluck('supplier_stock_item_id');
            $supplier->stockItems()->where('is_active', true)->whereNotIn('id', $existingIds)->each(
                fn (SupplierStockItem $item) => $session->items()->create([
                    'supplier_stock_item_id' => $item->id,
                    'previous_quantity' => $item->current_quantity,
                    'check_status' => 'not_checked',
                ])
            );

            return $session;
        });

        return response()->json(['data' => $this->sessionPayload($session)]);
    }

    public function addItem(Request $request, StockSession $stockSession): JsonResponse
    {
        abort_if($stockSession->status !== 'draft', 409, 'Submitted sessions cannot be changed.');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'quantity' => ['required', 'integer', 'min:0'], 'product_id' => ['nullable', 'exists:products,id']]);

        $item = SupplierStockItem::query()->firstOrCreate(
            ['supplier_id' => $stockSession->supplier_id, 'name' => trim($data['name'])],
            ['product_id' => $data['product_id'] ?? null, 'current_quantity' => 0, 'is_active' => true]
        );
        $entry = $stockSession->items()->firstOrCreate(
            ['supplier_stock_item_id' => $item->id],
            ['previous_quantity' => $item->current_quantity]
        );
        $entry->update(['quantity' => $data['quantity'], 'check_status' => 'new', 'checked_by' => $request->user()?->id, 'checked_at' => now()]);

        return response()->json(['message' => 'Stock item added.', 'data' => $this->sessionPayload($stockSession)], 201);
    }

    public function updateItem(Request $request, StockSession $stockSession, StockSessionItem $stockSessionItem): JsonResponse
    {
        abort_unless($stockSessionItem->stock_session_id === $stockSession->id, 404);
        abort_if($stockSession->status !== 'draft', 409, 'Submitted sessions cannot be changed.');
        $data = $request->validate(['action' => ['required', 'in:same,change,zero,not_found'], 'quantity' => ['nullable', 'integer', 'min:0'], 'note' => ['nullable', 'string', 'max:1000']]);
        $quantity = match ($data['action']) {
            'same' => $stockSessionItem->previous_quantity,
            'zero' => 0,
            'not_found' => null,
            default => $data['quantity'] ?? null,
        };
        abort_if($data['action'] === 'change' && $quantity === null, 422, 'Quantity is required when changing stock.');
        $stockSessionItem->update(['quantity' => $quantity, 'check_status' => $data['action'] === 'change' ? 'changed' : $data['action'], 'note' => $data['note'] ?? null, 'checked_by' => $request->user()?->id, 'checked_at' => now()]);

        return response()->json(['message' => 'Item checked.', 'data' => $stockSessionItem->fresh()->load('stockItem.currentPhoto')]);
    }

    public function uploadPhoto(Request $request, SupplierStockItem $supplierStockItem): JsonResponse
    {
        $data = $request->validate(['photo' => ['required', 'image', 'max:20480'], 'kind' => ['nullable', 'in:bundle,vendor,catalog_candidate']]);
        $file = $data['photo'];
        $kind = $data['kind'] ?? 'bundle';
        $disk = config('scak.storage.disk', 'products');
        $path = $file->store('stock-feedback/'.$supplierStockItem->supplier_id.'/'.$supplierStockItem->id, $disk);
        if ($kind === 'bundle') {
            $supplierStockItem->photos()->where('kind', 'bundle')->update(['is_current' => false]);
        }
        $photo = $supplierStockItem->photos()->create(['kind' => $kind, 'disk' => $disk, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'bytes' => $file->getSize(), 'is_current' => true, 'uploaded_by' => $request->user()?->id]);

        return response()->json(['message' => 'Photo uploaded.', 'data' => $photo], 201);
    }

    public function submit(Request $request, StockSession $stockSession): JsonResponse
    {
        abort_if($stockSession->status !== 'draft', 409, 'This session has already been submitted.');
        abort_if($stockSession->items()->where('check_status', 'not_checked')->exists(), 422, 'Every item must be checked before submission.');

        DB::transaction(function () use ($stockSession, $request): void {
            $stockSession->items()->whereNotNull('quantity')->each(function (StockSessionItem $entry) use ($request): void {
                $entry->stockItem()->update(['current_quantity' => $entry->quantity, 'last_counted_at' => now(), 'last_counted_by' => $request->user()?->id]);
            });
            $stockSession->update(['status' => 'submitted', 'submitted_by' => $request->user()?->id, 'submitted_at' => now()]);
        });

        return response()->json(['message' => 'Daily stock feedback submitted.', 'data' => $this->sessionPayload($stockSession)]);
    }

    private function sessionPayload(StockSession $session): StockSession
    {
        return $session->fresh()->load(['supplier', 'items.stockItem.currentPhoto', 'items.stockItem.product']);
    }
}
