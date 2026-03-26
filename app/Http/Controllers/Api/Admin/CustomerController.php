<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderRequestItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->withCount('orderRequests')
            ->addSelect([
                'total_items_ordered' => OrderRequestItem::query()
                    ->selectRaw('COALESCE(SUM(order_request_items.quantity), 0)')
                    ->join('order_requests', 'order_requests.id', '=', 'order_request_items.order_request_id')
                    ->whereColumn('order_requests.user_id', 'users.id'),
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%');
                });
            })
            ->latest('last_login_at')
            ->paginate(50)
            ->through(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'city' => $customer->city,
                'last_login_at' => optional($customer->last_login_at)?->toIso8601String(),
                'total_orders' => $customer->order_requests_count,
                'total_items_ordered' => (int) ($customer->total_items_ordered ?? 0),
            ]);

        return response()->json($customers);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->role === User::ROLE_CUSTOMER, 404);

        $user->load([
            'orderRequests.items',
        ]);

        $orders = $user->orderRequests
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($order) => [
                'id' => $order->id,
                'reference_code' => $order->reference_code,
                'status' => $order->status,
                'note' => $order->note,
                'internal_notes' => $order->internal_notes,
                'created_at' => optional($order->created_at)?->toIso8601String(),
                'items_count' => $order->items->sum('quantity'),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_snapshot_name' => $item->product_snapshot_name,
                    'product_snapshot_sku' => $item->product_snapshot_sku,
                    'unit_price_snapshot' => (float) $item->unit_price_snapshot,
                    'quantity' => $item->quantity,
                ])->values(),
            ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'city' => $user->city,
                'last_login_at' => optional($user->last_login_at)?->toIso8601String(),
                'total_orders' => $orders->count(),
                'total_items_ordered' => $orders->sum('items_count'),
                'latest_order_at' => optional($user->orderRequests->sortByDesc('created_at')->first()?->created_at)?->toIso8601String(),
                'orders' => $orders,
            ],
        ]);
    }
}
