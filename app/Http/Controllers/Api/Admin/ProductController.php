<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCreateProductsRequest;
use App\Http\Requests\BulkUpdateProductStatusRequest;
use App\Http\Requests\UpsertProductRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductUpsertService $productUpsertService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images'])
            ->when(! $request->boolean('include_archived', true), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('tag'), fn ($query) => $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $request->string('tag')->toString())))
            ->latest()
            ->paginate((int) $request->integer('per_page', 50))
            ->through(fn (Product $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function store(UpsertProductRequest $request): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), null)
            ->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images']);

        $this->auditLogService->record('product.created', $request->user(), $product, [
            'name' => $product->name,
            'status' => $product->status,
        ], $request);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $this->transformProduct($product),
        ], 201);
    }

    public function update(UpsertProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), $product)
            ->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images']);

        $this->auditLogService->record('product.updated', $request->user(), $product, [
            'name' => $product->name,
            'status' => $product->status,
        ], $request);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $this->transformProduct($product),
        ]);
    }

    public function bulkStore(BulkCreateProductsRequest $request): JsonResponse
    {
        $products = DB::transaction(function () use ($request) {
            return collect($request->validated('products'))
                ->map(fn (array $payload) => $this->productUpsertService->upsert($payload));
        });

        $this->auditLogService->record('product.bulk_created', $request->user(), null, [
            'count' => $products->count(),
            'product_ids' => $products->pluck('id')->values(),
        ], $request);

        return response()->json([
            'message' => 'Products created successfully.',
            'data' => $products->map(fn (Product $product) => $this->transformProduct($product))->values(),
        ], 201);
    }

    public function bulkStatus(BulkUpdateProductStatusRequest $request): JsonResponse
    {
        $status = $request->string('status')->toString();
        $products = Product::query()->whereIn('id', $request->validated('product_ids'))->get();

        foreach ($products as $product) {
            $product->update([
                'status' => $status,
                'is_active' => $status === 'active',
                'published_at' => $status === 'active' ? ($product->published_at ?? now()) : null,
            ]);
        }

        $this->auditLogService->record('product.bulk_status_updated', $request->user(), null, [
            'status' => $status,
            'product_ids' => $products->pluck('id')->values(),
        ], $request);

        return response()->json([
            'message' => 'Product statuses updated successfully.',
            'data' => Product::query()
                ->with(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images'])
                ->whereIn('id', $products->pluck('id'))
                ->get()
                ->map(fn (Product $product) => $this->transformProduct($product))
                ->values(),
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
            'tags' => $product->tags->pluck('name')->values(),
            'created_at' => optional($product->created_at)?->toIso8601String(),
            'published_at' => optional($product->published_at)?->toIso8601String(),
            'images' => $product->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'is_cover' => $image->is_cover,
                'sort_order' => $image->sort_order,
            ])->values(),
        ];
    }
}
