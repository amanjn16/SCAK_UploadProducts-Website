<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    public function index(): JsonResponse
    {
        $priceBounds = Product::query()
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
            ->where('price', '>', 0)
            ->first();

        return response()->json([
            'tags' => Tag::query()->withCount('products')->orderBy('name')->get(),
            'price' => [
                'min' => (float) ($priceBounds?->min_price ?? 0),
                'max' => (float) ($priceBounds?->max_price ?? 0),
            ],
        ]);
    }
}
