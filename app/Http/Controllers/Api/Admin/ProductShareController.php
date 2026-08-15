<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateShareImagesRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class ProductShareController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function store(GenerateShareImagesRequest $request): JsonResponse
    {
        $products = Product::query()
            ->with(['images', 'tags'])
            ->whereIn('id', $request->validated('product_ids'))
            ->get();

        $this->auditLogService->record(
            'product.share_images',
            $request->user(),
            null,
            [
                'product_ids' => $products->pluck('id')->values(),
                'include_rate_overlay' => $request->boolean('include_rate_overlay'),
            ],
            $request,
        );

        return response()->json([
            'message' => 'Share export prepared successfully.',
            'data' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'tags' => $product->tags->pluck('name')->values(),
                'images' => $product->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_cover' => $image->is_cover,
                ])->values(),
            ])->values(),
        ]);
    }
}
