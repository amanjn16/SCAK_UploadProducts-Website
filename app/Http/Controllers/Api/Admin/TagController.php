<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TagUpsertRequest;
use App\Models\Tag;
use App\Services\AuditLogService;
use App\Services\CatalogCacheService;
use App\Services\ProductUpsertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ProductUpsertService $productUpsertService,
        private readonly CatalogCacheService $catalogCacheService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tags = Tag::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => $tag->products_count,
            ]);

        return response()->json(['data' => $tags]);
    }

    public function store(TagUpsertRequest $request): JsonResponse
    {
        $normalizedName = $this->normalizeTagName($request->string('name')->toString());

        $tag = Tag::query()->firstOrCreate([
            'name' => $normalizedName,
        ], [
            'slug' => Str::slug($normalizedName),
        ]);

        $this->auditLogService->record(
            $tag->wasRecentlyCreated ? 'tag.created' : 'tag.reused',
            $request->user(),
            $tag,
            ['name' => $tag->name],
            $request
        );
        $this->catalogCacheService->bump();

        return response()->json([
            'message' => $tag->wasRecentlyCreated ? 'Tag created successfully.' : 'Tag already exists.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => 0,
            ],
        ], 201);
    }

    public function update(TagUpsertRequest $request, Tag $tag): JsonResponse
    {
        $normalizedName = $this->normalizeTagName($request->string('name')->toString());
        $normalizedSlug = Str::slug($normalizedName);

        $existingTag = Tag::query()
            ->where('id', '!=', $tag->id)
            ->where(function ($query) use ($normalizedName, $normalizedSlug) {
                $query->where('name', $normalizedName)
                    ->orWhere('slug', $normalizedSlug);
            })
            ->first();

        if ($existingTag) {
            return DB::transaction(function () use ($existingTag, $request, $tag) {
                $productIds = $tag->products()->pluck('products.id')->all();
                $existingTag->products()->syncWithoutDetaching($productIds);
                $tag->products()->detach();
                $tag->delete();

                $products = $existingTag->products()
                    ->with(['tags', 'sizes', 'features', 'supplier', 'city', 'category', 'topFabric', 'dupattaFabric'])
                    ->get();

                foreach ($products as $product) {
                    $this->productUpsertService->syncSearchText($product);
                }

                $this->auditLogService->record('tag.merged', $request->user(), $existingTag, [
                    'merged_from_id' => $tag->id,
                    'merged_from_name' => $tag->name,
                    'merged_into_id' => $existingTag->id,
                    'merged_into_name' => $existingTag->name,
                ], $request);
                $this->catalogCacheService->bump();

                return response()->json([
                    'message' => 'Tag merged successfully.',
                    'data' => [
                        'id' => $existingTag->id,
                        'name' => $existingTag->name,
                        'slug' => $existingTag->slug,
                        'products_count' => $existingTag->products()->count(),
                    ],
                ]);
            });
        }

        $tag->update([
            'name' => $normalizedName,
            'slug' => $normalizedSlug,
        ]);

        $tag->load('products.tags', 'products.sizes', 'products.features', 'products.supplier', 'products.city', 'products.category', 'products.topFabric', 'products.dupattaFabric');
        foreach ($tag->products as $product) {
            $this->productUpsertService->syncSearchText($product);
        }

        $this->auditLogService->record('tag.updated', $request->user(), $tag, ['name' => $tag->name], $request);
        $this->catalogCacheService->bump();

        return response()->json([
            'message' => 'Tag updated successfully.',
            'data' => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'products_count' => $tag->products()->count(),
            ],
        ]);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        $tag->load('products.tags', 'products.sizes', 'products.features', 'products.supplier', 'products.city', 'products.category', 'products.topFabric', 'products.dupattaFabric');
        $meta = [
            'name' => $tag->name,
            'products_count' => $tag->products()->count(),
        ];
        $products = $tag->products;
        $tag->products()->detach();
        $tag->delete();

        foreach ($products as $product) {
            $this->productUpsertService->syncSearchText($product->load('tags', 'sizes', 'features', 'supplier', 'city', 'category', 'topFabric', 'dupattaFabric'));
        }

        $this->auditLogService->record('tag.deleted', $request->user(), null, $meta, $request);
        $this->catalogCacheService->bump();

        return response()->json(['message' => 'Tag deleted successfully.']);
    }

    private function normalizeTagName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->squish()
            ->lower()
            ->title()
            ->toString();
    }
}
