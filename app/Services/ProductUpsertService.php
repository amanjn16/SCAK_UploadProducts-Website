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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductUpsertService
{
    public function upsert(array $payload, ?Product $product = null): Product
    {
        $product ??= new Product();
        $normalizedName = $this->normalizeDisplayName($payload['name']);

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
            'name' => $normalizedName,
            'slug' => $product->exists ? $product->slug : $this->generateUniqueSlug($normalizedName),
            'sku' => $payload['sku'] ?? $this->generateUniqueSku($normalizedName),
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
            ->map(fn (string $name) => $this->firstOrCreateTagByName($name)->id);

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
    public function addImages(Product $product, array $uploadedImages, ?int $coverIndex = null, bool $alreadyWatermarked = false): Product
    {
        $disk = config('scak.storage.disk', 'products');
        $baseOrder = (int) $product->images()->max('sort_order');

        foreach ($uploadedImages as $index => $uploadedImage) {
            $path = $this->storeProductImage($product, $uploadedImage, $alreadyWatermarked);

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

    public function delete(Product $product): void
    {
        $product->loadMissing('images', 'tags', 'sizes', 'features');

        foreach ($product->images as $image) {
            if (Storage::disk($image->disk)->exists($image->path)) {
                Storage::disk($image->disk)->delete($image->path);
            }
        }

        $product->tags()->detach();
        $product->sizes()->detach();
        $product->features()->detach();
        $product->forceDelete();
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

    protected function firstOrCreateTagByName(string $name): Tag
    {
        $normalizedName = $this->normalizeDisplayName($name);

        return Tag::query()->firstOrCreate(
            ['slug' => Str::slug($normalizedName)],
            ['name' => $normalizedName],
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
        $digits = 4;

        while (true) {
            $min = (10 ** ($digits - 1));
            $max = (10 ** $digits) - 1;
            $candidate = 'S'.random_int($min, $max);

            if (! Product::query()->where('sku', $candidate)->exists()) {
                return $candidate;
            }

            $digits++;
        }
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

    protected function normalizeDisplayName(string $name): string
    {
        return Str::of($name)
            ->trim()
            ->squish()
            ->lower()
            ->title()
            ->toString();
    }

    protected function storeProductImage(Product $product, UploadedFile $uploadedImage, bool $alreadyWatermarked = false): string
    {
        $disk = config('scak.storage.disk', 'products');
        $fileName = Str::uuid().'.jpg';
        $path = $product->slug.'/'.$fileName;
        $binary = $alreadyWatermarked
            ? $uploadedImage->get()
            : $this->renderImageWithSkuWatermark($uploadedImage, $product->sku);

        Storage::disk($disk)->put($path, $binary);

        return $path;
    }

    protected function renderImageWithSkuWatermark(UploadedFile $uploadedImage, ?string $sku): string
    {
        $raw = $uploadedImage->get();

        if (blank($sku) || ! function_exists('imagecreatefromstring')) {
            return $raw;
        }

        $image = @imagecreatefromstring($raw);

        if (! $image) {
            return $raw;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $font = 1;
        $margin = max(6, (int) round(min($width, $height) * 0.012));
        $textHeight = imagefontheight($font);
        $y = max(0, $height - $textHeight - $margin);
        $shadowColor = imagecolorallocatealpha($image, 0, 0, 0, 70);
        $textColor = imagecolorallocatealpha($image, 255, 255, 255, 20);

        imagestring($image, $font, $margin + 1, $y + 1, $sku, $shadowColor);
        imagestring($image, $font, $margin, $y, $sku, $textColor);

        ob_start();
        imagejpeg($image, null, 88);
        $binary = ob_get_clean() ?: $raw;

        imagedestroy($image);

        return $binary;
    }
}
