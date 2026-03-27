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
            ->with(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images'])
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

        $product->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images']);

        return response()->json([
            'data' => $this->transformProduct($product, true),
        ]);
    }

    protected function transformProduct(Product $product, bool $detailed = false): array
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
            'legacy_published_at' => optional($product->legacy_published_at)?->toIso8601String(),
            'legacy_modified_at' => optional($product->legacy_modified_at)?->toIso8601String(),
            'images' => $detailed
                ? $product->images->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'is_cover' => $image->is_cover,
                    'sort_order' => $image->sort_order,
                ])->values()
                : [],
        ];
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
