<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\OrderRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRequestController extends Controller
{
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $products = Product::query()
            ->whereIn('id', collect($validated['items'])->pluck('product_id'))
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        abort_if($products->count() !== count($validated['items']), 422, 'One or more selected products are unavailable.');

        $orderRequest = DB::transaction(function () use ($user, $validated, $products) {
            $orderRequest = OrderRequest::query()->create([
                'user_id' => $user->id,
                'reference_code' => 'SCAK-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'customer_name' => $user->name,
                'customer_phone' => $user->phone,
                'customer_city' => $user->city,
                'note' => $validated['note'] ?? null,
                'status' => 'new',
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products[(int) $item['product_id']];

                $orderRequest->items()->create([
                    'product_id' => $product->id,
                    'product_snapshot_name' => $product->name,
                    'product_snapshot_sku' => $product->sku,
                    'unit_price_snapshot' => $product->price,
                    'quantity' => $item['quantity'],
                ]);
            }

            return $orderRequest->load('items');
        });

        return response()->json([
            'message' => 'Bucket submitted successfully.',
            'reference_code' => $orderRequest->reference_code,
            'status' => $orderRequest->status,
        ], 201);
    }
}
