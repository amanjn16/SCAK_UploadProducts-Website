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
            ->with('items.product.images')
            ->when(
                $request->has('is_archived'),
                fn ($query) => $query->where('is_archived', $request->boolean('is_archived')),
                fn ($query) => $query->where('is_archived', false)
            )
            ->latest('updated_at')
            ->paginate(25);

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
            'data' => $orderRequest->fresh('items'),
        ]);
    }
}
