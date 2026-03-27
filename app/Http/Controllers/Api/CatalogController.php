<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
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
                'created_at',
                'published_at',
                'legacy_published_at',
                'legacy_modified_at',
            ])
            ->with([
                'coverImage:id,product_id,disk,path,original_name,sort_order,is_cover',
                'firstImage:id,product_id,disk,path,original_name,sort_order,is_cover',
            ])
            ->when(! $request->boolean('include_archived'), fn ($query) => $query->where('is_active', true), fn ($query) => $query)
            ->when($request->filled('ids'), function ($query) use ($request) {
                $ids = collect(explode(',', (string) $request->string('ids')))->filter()->map(fn ($id) => (int) $id);
                $query->whereIn('id', $ids);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhereHas('tags', fn ($tagQuery) => $tagQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($this->selectedTagSlugs($request) !== [], function ($query) use ($request) {
                $tagSlugs = $this->selectedTagSlugs($request);

                $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereIn('slug', $tagSlugs));
            })
            ->when($request->filled('min_price'), fn ($query) => $query->where('price', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($query) => $query->where('price', '<=', $request->float('max_price')))
            ->when($request->string('sort')->toString() === 'price_low', fn ($query) => $query->orderBy('price'))
            ->when($request->string('sort')->toString() === 'price_high', fn ($query) => $query->orderByDesc('price'))
            ->when($request->string('sort')->toString() === 'title', fn ($query) => $query->orderBy('name'))
            ->when(! in_array($request->string('sort')->toString(), ['price_low', 'price_high', 'title'], true), fn ($query) => $query->latest('published_at')->latest())
            ->paginate((int) $request->integer('per_page', 24))
            ->through(fn (Product $product) => $this->transformProduct($product));

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active || request()->boolean('include_archived'), 404);

        $product->load([
            'tags:id,name,slug',
            'images:id,product_id,disk,path,original_name,sort_order,is_cover',
            'coverImage:id,product_id,disk,path,original_name,sort_order,is_cover',
            'firstImage:id,product_id,disk,path,original_name,sort_order,is_cover',
        ]);

        return response()->json([
            'data' => $this->transformProduct($product, true),
        ]);
    }

    protected function transformProduct(Product $product, bool $detailed = false): array
    {
        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'cover_image_url' => $product->cover_image_url,
            'status' => $product->status,
            'is_active' => $product->is_active,
            'created_at' => optional($product->created_at)?->toIso8601String(),
            'published_at' => optional($product->published_at)?->toIso8601String(),
            'legacy_published_at' => optional($product->legacy_published_at)?->toIso8601String(),
            'legacy_modified_at' => optional($product->legacy_modified_at)?->toIso8601String(),
        ];

        if (! $detailed) {
            return $payload;
        }

        $payload['description'] = $product->description;
        $payload['tags'] = $product->tags->pluck('name')->values();
        $payload['images'] = $product->images->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->url,
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
