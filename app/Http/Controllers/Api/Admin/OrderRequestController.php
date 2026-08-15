<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderRequestStatusRequest;
use App\Models\OrderRequest;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class OrderRequestController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $orders = OrderRequest::query()
            ->with([
                'items.product.coverImage',
                'items.product.firstImage',
            ])
            ->when(
                $request->has('is_archived'),
                fn ($query) => $query->where('is_archived', $request->boolean('is_archived')),
                fn ($query) => $query->where('is_archived', false)
            )
            ->latest('updated_at')
            ->paginate(25)
            ->through(fn (OrderRequest $orderRequest) => $this->transformOrder($orderRequest));

        return response()->json($orders);
    }

    public function update(UpdateOrderRequestStatusRequest $request, OrderRequest $orderRequest): JsonResponse
    {
        $status = $request->input('status', $orderRequest->status);
        $isArchived = $request->boolean('is_archived', $orderRequest->is_archived);

        $orderRequest->fill([
            'status' => $status,
            'is_archived' => $isArchived,
            'internal_notes' => $request->input('internal_notes'),
            'contacted_at' => $status === 'contacted' ? now() : $orderRequest->contacted_at,
            'confirmed_at' => $status === 'confirmed' ? now() : $orderRequest->confirmed_at,
            'paid_offline_at' => $status === 'paid_offline' ? now() : $orderRequest->paid_offline_at,
            'dispatched_at' => $status === 'dispatched' ? now() : $orderRequest->dispatched_at,
            'completed_at' => $status === 'completed' ? now() : $orderRequest->completed_at,
            'cancelled_at' => $status === 'cancelled' ? now() : $orderRequest->cancelled_at,
        ])->save();

        $this->auditLogService->record('order.updated', $request->user(), $orderRequest, [
            'status' => $status,
            'is_archived' => $isArchived,
            'reference_code' => $orderRequest->reference_code,
        ], $request);

        return response()->json([
            'message' => 'Order request updated successfully.',
            'data' => $this->transformOrder($orderRequest->fresh([
                'items.product.coverImage',
                'items.product.firstImage',
            ])),
        ]);
    }

    protected function transformOrder(OrderRequest $orderRequest): array
    {
        return [
            'id' => $orderRequest->id,
            'reference_code' => $orderRequest->reference_code,
            'customer_name' => $orderRequest->customer_name,
            'customer_phone' => $orderRequest->customer_phone,
            'customer_city' => $orderRequest->customer_city,
            'note' => $orderRequest->note,
            'internal_notes' => $orderRequest->internal_notes,
            'status' => $orderRequest->status,
            'is_archived' => (bool) $orderRequest->is_archived,
            'items' => $orderRequest->items->map(fn ($item) => [
                'id' => $item->id,
                'product_snapshot_name' => $item->product_snapshot_name,
                'product_snapshot_sku' => $item->product_snapshot_sku,
                'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                'quantity' => (int) $item->quantity,
                'product_image_url' => $item->product?->cover_image_thumb_url ?? $item->product?->cover_image_url,
            ])->values(),
        ];
    }
}
