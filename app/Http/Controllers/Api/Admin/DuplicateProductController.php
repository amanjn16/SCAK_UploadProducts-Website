<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditLogService;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DuplicateProductController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly CatalogCacheService $cache,
    ) {}

    public function index(): JsonResponse
    {
        $hashes = ProductImage::query()
            ->whereNotNull('sha256')->whereHas('product')
            ->select('sha256')->groupBy('sha256')
            ->havingRaw('COUNT(DISTINCT product_id) > 1')
            ->orderByRaw('COUNT(DISTINCT product_id) DESC')->limit(200)->pluck('sha256');

        $groups = $hashes->map(function (string $hash): array {
            $images = ProductImage::query()->with(['product.images'])
                ->where('sha256', $hash)->whereHas('product')->orderBy('product_id')->get();
            $products = $images->pluck('product')->unique('id')->values()->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'brand' => $product->brand,
                'price' => $product->price,
                'product_type' => $product->product_type,
                'image_count' => $product->images->count(),
                'matching_image_url' => $images->firstWhere('product_id', $product->id)?->thumb_url,
            ]);

            return ['sha256' => $hash, 'products' => $products];
        })->filter(fn (array $group) => count($group['products']) > 1)->values();

        return response()->json(['data' => $groups, 'meta' => ['groups' => $groups->count()]]);
    }

    public function merge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'keep_product_id' => ['required', 'integer', 'exists:products,id'],
            'merge_product_id' => ['required', 'integer', 'different:keep_product_id', 'exists:products,id'],
            'sha256' => ['required', 'size:64'],
        ]);
        $keep = Product::query()->findOrFail($data['keep_product_id']);
        $merge = Product::query()->findOrFail($data['merge_product_id']);
        abort_unless($this->shareHash($keep, $merge, $data['sha256']), 422, 'These products no longer share that exact image.');

        DB::transaction(function () use ($keep, $merge): void {
            $keep->tags()->syncWithoutDetaching($merge->tags()->pluck('tags.id'));
            $keep->sizes()->syncWithoutDetaching($merge->sizes()->pluck('sizes.id'));
            $keep->features()->syncWithoutDetaching($merge->features()->pluck('features.id'));

            $existingHashes = $keep->images()->whereNotNull('sha256')->pluck('sha256')->all();
            $nextOrder = (int) $keep->images()->max('sort_order');
            foreach ($merge->images()->orderBy('sort_order')->get() as $image) {
                if (! $image->sha256 || ! in_array($image->sha256, $existingHashes, true)) {
                    $image->forceFill(['product_id' => $keep->id, 'is_cover' => false, 'sort_order' => ++$nextOrder])->save();
                    if ($image->sha256) {
                        $existingHashes[] = $image->sha256;
                    }
                }
            }

            DB::table('order_request_items')->where('product_id', $merge->id)->update(['product_id' => $keep->id]);
            DB::table('supplier_stock_items')->where('product_id', $merge->id)->update(['product_id' => $keep->id]);

            $keep->forceFill([
                'product_type' => $keep->product_type === 'sale' || $merge->product_type === 'sale' ? 'sale' : 'regular',
                'brand' => $keep->brand ?: $merge->brand,
                'brand_price' => $keep->brand_price ?: $merge->brand_price,
                'remarks' => $keep->remarks ?: $merge->remarks,
            ])->save();
            $merge->forceFill(['merged_into_product_id' => $keep->id, 'is_active' => false, 'status' => 'archived'])->save();
            $merge->delete();
        });

        $this->cache->bump();
        $this->audit->record('product.duplicate_merged', $request->user(), $keep, ['merged_product_id' => $merge->id], $request);

        return response()->json(['message' => 'Products merged. The duplicate is archived and remains recoverable.']);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->images()->whereNotNull('sha256')->whereIn('sha256', function ($query): void {
            $query->from('product_images')->select('sha256')->whereNotNull('sha256')->groupBy('sha256')->havingRaw('COUNT(DISTINCT product_id) > 1');
        })->exists(), 422, 'This product is no longer in an exact-image duplicate group.');

        $product->forceFill(['is_active' => false, 'status' => 'archived'])->save();
        $product->delete();
        $this->cache->bump();
        $this->audit->record('product.duplicate_archived', $request->user(), $product, [], $request);

        return response()->json(['message' => 'Duplicate archived. Its data and images remain recoverable.']);
    }

    private function shareHash(Product $first, Product $second, string $hash): bool
    {
        return $first->images()->where('sha256', $hash)->exists() && $second->images()->where('sha256', $hash)->exists();
    }
}
