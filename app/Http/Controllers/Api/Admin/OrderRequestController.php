<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderRequestStatusRequest;
use App\Models\OrderRequest;
use Illuminate\Http\JsonResponse;

class OrderRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = OrderRequest::query()
            ->with('items.product.images')
            ->latest()
            ->paginate(25);

        return response()->json($orders);
    }

    public function update(UpdateOrderRequestStatusRequest $request, OrderRequest $orderRequest): JsonResponse
    {
        $status = $request->string('status')->toString();

        $orderRequest->fill([
            'status' => $status,
            'internal_notes' => $request->input('internal_notes'),
            'contacted_at' => $status === 'contacted' ? now() : $orderRequest->contacted_at,
            'confirmed_at' => $status === 'confirmed' ? now() : $orderRequest->confirmed_at,
            'paid_offline_at' => $status === 'paid_offline' ? now() : $orderRequest->paid_offline_at,
            'dispatched_at' => $status === 'dispatched' ? now() : $orderRequest->dispatched_at,
            'completed_at' => $status === 'completed' ? now() : $orderRequest->completed_at,
            'cancelled_at' => $status === 'cancelled' ? now() : $orderRequest->cancelled_at,
        ])->save();

        return response()->json([
            'message' => 'Order request updated successfully.',
            'data' => $orderRequest->fresh('items'),
        ]);
    }
}
