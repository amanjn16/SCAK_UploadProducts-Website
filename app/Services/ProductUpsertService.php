<?php

namespace App\Services;

use App\Models\Category;
use App\Models\City;
use App\Models\Fabric;
use App\Models\Feature;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Size;
use App\Models\Supplier;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductUpsertService
{
    public function upsert(array $payload, ?Product $product = null): Product
    {
        $product ??= new Product();

        $supplier = filled($payload['supplier'] ?? null)
            ? $this->firstOrCreateByName(Supplier::class, $payload['supplier'])
            : null;
        $city = filled($payload['city'] ?? null)
            ? $this->firstOrCreateByName(City::class, $payload['city'])
            : null;
        $category = filled($payload['category'] ?? null)
            ? $this->firstOrCreateByName(Category::class, $payload['category'])
            : null;
        $topFabric = filled($payload['top_fabric'] ?? null)
            ? $this->firstOrCreateByName(Fabric::class, $payload['top_fabric'], ['type' => 'top'])
            : null;
        $dupattaFabric = filled($payload['dupatta_fabric'] ?? null)
            ? $this->firstOrCreateByName(Fabric::class, $payload['dupatta_fabric'], ['type' => 'dupatta'])
            : null;

        $product->fill([
            'name' => $payload['name'],
            'slug' => $product->exists ? $product->slug : $this->generateUniqueSlug($payload['name']),
            'sku' => $payload['sku'] ?? $this->generateUniqueSku($payload['name']),
            'price' => $payload['price'],
            'supplier_id' => $supplier?->id,
            'city_id' => $city?->id,
            'category_id' => $category?->id,
            'top_fabric_id' => $topFabric?->id,
            'dupatta_fabric_id' => $dupattaFabric?->id,
            'description' => $payload['description'] ?? null,
            'status' => $payload['status'] ?? 'active',
            'is_active' => ($payload['status'] ?? 'active') === 'active',
            'published_at' => ($payload['status'] ?? 'active') === 'active'
                ? ($product->published_at ?? now())
                : null,
        ]);

        if ($product->isDirty('sku') && filled($product->sku)) {
            $product->sku = $this->ensureUniqueSku($product->sku, $product->id);
        }

        $product->save();

        $sizeIds = collect($payload['sizes'] ?? [])
            ->filter()
            ->map(fn (string $name) => $this->firstOrCreateByName(Size::class, $name)->id);

        $featureIds = collect($payload['features'] ?? [])
            ->filter()
            ->map(fn (string $name) => $this->firstOrCreateByName(Feature::class, $name)->id);

        $tagIds = collect($payload['tags'] ?? [])
            ->filter()
            ->map(fn (string $name) => $this->firstOrCreateByName(Tag::class, $name)->id);

        $product->sizes()->sync($sizeIds);
        $product->features()->sync($featureIds);
        $product->tags()->sync($tagIds);

        if (! empty($payload['image_order'] ?? [])) {
            $this->reorderImages($product, collect($payload['image_order']), $payload['cover_image_id'] ?? null);
        } elseif (! empty($payload['cover_image_id'] ?? null)) {
            $this->markCoverImage($product, (int) $payload['cover_image_id']);
        }

        return $product->load(['supplier', 'city', 'category', 'topFabric', 'dupattaFabric', 'sizes', 'features', 'tags', 'images']);
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedImages
     */
    public function addImages(Product $product, array $uploadedImages, ?int $coverIndex = null): Product
    {
        $disk = config('scak.storage.disk', 'products');
        $baseOrder = (int) $product->images()->max('sort_order');

        foreach ($uploadedImages as $index => $uploadedImage) {
            $fileName = Str::uuid().'.'.$uploadedImage->getClientOriginalExtension();
            $path = $uploadedImage->storeAs($product->slug, $fileName, $disk);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $uploadedImage->getClientOriginalName(),
                'sort_order' => $baseOrder + $index + 1,
                'is_cover' => $coverIndex === $index || (! $product->images()->exists() && $index === 0),
            ]);
        }

        if ($coverIndex !== null) {
            $coverImage = $product->images()->orderBy('sort_order')->skip($coverIndex)->first();

            if ($coverImage) {
                $this->markCoverImage($product, $coverImage->id);
            }
        }

        return $product->load('images');
    }

    public function reorderImages(Product $product, Collection $imageOrder, ?int $coverImageId = null): void
    {
        $imageOrder->values()->each(function (int $imageId, int $index) use ($product): void {
            $product->images()->whereKey($imageId)->update(['sort_order' => $index + 1]);
        });

        if ($coverImageId) {
            $this->markCoverImage($product, $coverImageId);
        }
    }

    public function markCoverImage(Product $product, int $coverImageId): void
    {
        $product->images()->update(['is_cover' => false]);
        $product->images()->whereKey($coverImageId)->update(['is_cover' => true]);
    }

    protected function firstOrCreateByName(string $modelClass, string $name, array $extra = [])
    {
        return $modelClass::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            array_merge(['name' => $name], $extra),
        );
    }

    protected function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    protected function generateUniqueSku(string $name): string
    {
        return $this->ensureUniqueSku('SCAK-'.Str::upper(Str::substr(Str::slug($name, ''), 0, 6)).random_int(1000, 9999));
    }

    protected function ensureUniqueSku(string $sku, ?int $ignoreProductId = null): string
    {
        $candidate = Str::upper($sku);
        $counter = 1;

        while (
            Product::query()
                ->when($ignoreProductId, fn ($query) => $query->whereKeyNot($ignoreProductId))
                ->where('sku', $candidate)
                ->exists()
        ) {
            $candidate = Str::upper($sku).'-'.$counter;
            $counter++;
        }

        return $candidate;
    }
}
