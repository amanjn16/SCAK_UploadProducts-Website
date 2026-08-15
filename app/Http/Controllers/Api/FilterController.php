<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tag;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class FilterController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $catalogCacheService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            $this->catalogCacheService->remember(
                'filters',
                [],
                (int) config('scak.cache.filters_ttl_seconds', 1800),
                function () {
                    $priceBounds = Product::query()
                        ->selectRaw('MIN(price) as min_price, MAX(price) as max_price')
                        ->where('price', '>', 0)
                        ->first();

                    return [
                        'tags' => Tag::query()->withCount('products')->orderBy('name')->get(),
                        'price' => [
                            'min' => (float) ($priceBounds?->min_price ?? 0),
                            'max' => (float) ($priceBounds?->max_price ?? 0),
                        ],
                    ];
                },
            )
        );
    }
}
