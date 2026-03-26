<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertProductRequest;
use App\Models\Product;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductUpsertService $productUpsertService) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'images'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate((int) $request->integer('per_page', 50))
            ->through(fn (Product $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function store(UpsertProductRequest $request): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), null)
            ->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'images']);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $this->transformProduct($product),
        ], 201);
    }

    public function update(UpsertProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), $product)
            ->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'images']);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $this->transformProduct($product),
        ]);
    }

    protected function transformProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'description' => $product->description,
            'cover_image_url' => $product->cover_image_url,
            'supplier' => $product->supplier?->name,
            'city' => $product->city?->name,
            'category' => $product->category?->name,
            'top_fabric' => $product->topFabric?->name,
            'dupatta_fabric' => $product->dupattaFabric?->name,
            'status' => $product->status,
            'is_active' => $product->is_active,
            'sizes' => $product->sizes->pluck('name')->values(),
            'features' => $product->features->pluck('name')->values(),
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_cover' => $image->is_cover,
                'sort_order' => $image->sort_order,
            ])->values(),
        ];
    }
}
