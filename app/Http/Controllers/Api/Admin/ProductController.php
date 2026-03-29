<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkCreateProductsRequest;
use App\Http\Requests\BulkDeleteProductsRequest;
use App\Http\Requests\BulkUpdateProductStatusRequest;
use App\Http\Requests\UpsertProductRequest;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\CatalogCacheService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductUpsertService $productUpsertService,
        private readonly AuditLogService $auditLogService,
        private readonly CatalogCacheService $catalogCacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->select([
                'id',
                'name',
                'slug',
                'sku',
                'price',
                'status',
                'is_active',
                'is_legacy_import',
                'legacy_wordpress_sku',
                'created_at',
                'published_at',
                'legacy_published_at',
                'legacy_modified_at',
                'search_text',
            ])
            ->with([
                'coverImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                'firstImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
            ])
            ->when(! $request->boolean('include_archived', true), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->float('max_price')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('search_text', 'like', '%'.mb_strtolower($search).'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('legacy_wordpress_sku', 'like', '%'.$search.'%');
                });
            })
            ->when($this->selectedTagSlugs($request) !== [], function ($query) use ($request) {
                $tagSlugs = $this->selectedTagSlugs($request);

                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('slug', $tagSlugs));
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', 50))
            ->through(fn (Product $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function store(UpsertProductRequest $request): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), null)
            ->load([
                'tags:id,name,slug',
                'images:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                'coverImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                'firstImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
            ]);

        $this->auditLogService->record('product.created', $request->user(), $product, [
            'name' => $product->name,
            'status' => $product->status,
        ], $request);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $this->transformProduct($product),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $this->transformProduct(
                $product->load([
                    'tags:id,name,slug',
                    'images:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                    'coverImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                    'firstImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                ]),
                true
            ),
        ]);
    }

    public function update(UpsertProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->productUpsertService->upsert($request->validated(), $product)
            ->load([
                'tags:id,name,slug',
                'images:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                'coverImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                'firstImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
            ]);

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
        $this->catalogCacheService->bump();

        return response()->json([
            'message' => 'Product statuses updated successfully.',
            'data' => Product::query()
                ->select([
                    'id',
                    'name',
                    'slug',
                    'sku',
                    'price',
                    'status',
                    'is_active',
                    'is_legacy_import',
                    'legacy_wordpress_sku',
                    'created_at',
                    'published_at',
                    'legacy_published_at',
                    'legacy_modified_at',
                    'search_text',
                ])
                ->with([
                    'coverImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                    'firstImage:id,product_id,disk,path,medium_path,thumb_path,original_name,mime_type,sort_order,is_cover',
                ])
                ->whereIn('id', $products->pluck('id'))
                ->get()
                ->map(fn (Product $product) => $this->transformProduct($product))
                ->values(),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        $meta = [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
        ];

        $this->productUpsertService->delete($product);

        $this->auditLogService->record('product.deleted', $request->user(), null, $meta, $request);

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    public function bulkDestroy(BulkDeleteProductsRequest $request): JsonResponse
    {
        $products = Product::query()
            ->whereIn('id', $request->validated('product_ids'))
            ->get();

        foreach ($products as $product) {
            $this->productUpsertService->delete($product);
        }

        $this->auditLogService->record('product.bulk_deleted', $request->user(), null, [
            'count' => $products->count(),
            'product_ids' => $products->pluck('id')->values(),
            'skus' => $products->pluck('sku')->filter()->values(),
        ], $request);

        return response()->json(['message' => 'Products deleted successfully.']);
    }

    protected function transformProduct(Product $product, bool $detailed = false): array
    {
        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'legacy_sku' => $product->legacy_wordpress_sku,
            'price' => (float) $product->price,
            'cover_image_url' => $product->cover_image_url,
            'cover_image_thumb_url' => $product->cover_image_thumb_url,
            'cover_image_original_url' => $product->cover_image_original_url,
            'pdf_url' => $product->pdf_url,
            'pdf_name' => $product->pdf_name,
            'status' => $product->status,
            'is_active' => $product->is_active,
            'is_legacy_import' => $product->is_legacy_import,
            'created_at' => optional($product->created_at)?->toIso8601String(),
            'published_at' => optional($product->published_at)?->toIso8601String(),
            'legacy_published_at' => optional($product->legacy_published_at)?->toIso8601String(),
            'legacy_modified_at' => optional($product->legacy_modified_at)?->toIso8601String(),
        ];

        if (! $detailed) {
            return $payload;
        }

        $payload['description'] = $product->description;
        $payload['remarks'] = $product->remarks;
        $payload['pdf_url'] = $product->pdf_url;
        $payload['pdf_name'] = $product->pdf_name;
        $payload['tags'] = $product->tags->pluck('name')->values();
        $payload['images'] = $product->images->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->url,
            'medium_url' => $image->medium_url,
            'thumb_url' => $image->thumb_url,
            'is_cover' => $image->is_cover,
            'sort_order' => $image->sort_order,
        ])->values();

        return $payload;
    }

    protected function selectedTagSlugs(Request $request): array
    {
        $tags = $request->input('tags', []);

        if (! is_array($tags)) {
            $tags = filled($tags) ? explode(',', (string) $tags) : [];
        }

        if ($request->filled('tag')) {
            $tags[] = $request->string('tag')->toString();
        }

        return collect($tags)
            ->filter(fn ($tag) => filled($tag))
            ->map(fn ($tag) => trim((string) $tag))
            ->unique()
            ->values()
            ->all();
    }
}
