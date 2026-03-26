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
            ->with(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'images'])
            ->where('is_active', true)
            ->when($request->filled('ids'), function ($query) use ($request) {
                $ids = collect(explode(',', (string) $request->string('ids')))->filter()->map(fn ($id) => (int) $id);
                $query->whereIn('id', $ids);
            })
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->when($request->filled('supplier'), fn ($query) => $query->whereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('slug', $request->string('supplier')->toString())))
            ->when($request->filled('city'), fn ($query) => $query->whereHas('city', fn ($cityQuery) => $cityQuery->where('slug', $request->string('city')->toString())))
            ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')->toString())))
            ->when($request->filled('top_fabric'), fn ($query) => $query->whereHas('topFabric', fn ($fabricQuery) => $fabricQuery->where('slug', $request->string('top_fabric')->toString())))
            ->when($request->filled('dupatta_fabric'), fn ($query) => $query->whereHas('dupattaFabric', fn ($fabricQuery) => $fabricQuery->where('slug', $request->string('dupatta_fabric')->toString())))
            ->when($request->filled('size'), fn ($query) => $query->whereHas('sizes', fn ($sizeQuery) => $sizeQuery->where('slug', $request->string('size')->toString())))
            ->when($request->filled('feature'), fn ($query) => $query->whereHas('features', fn ($featureQuery) => $featureQuery->where('slug', $request->string('feature')->toString())))
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
        abort_unless($product->is_active, 404);

        $product->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'images']);

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
            'sizes' => $product->sizes->pluck('name')->values(),
            'features' => $product->features->pluck('name')->values(),
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
}
