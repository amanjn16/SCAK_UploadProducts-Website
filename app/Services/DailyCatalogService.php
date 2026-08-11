<?php

namespace App\Services;

use App\Http\Controllers\Api\Admin\StorefrontSettingsController;
use App\Models\AppSetting;
use App\Models\Product;
use App\Models\ProductImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class DailyCatalogService
{
    public const FILE_PATH = 'daily-catalog/scak-daily-catalog.pdf';
    public const META_KEY = 'daily_catalog_meta';
    public const MAX_BYTES = 4_200_000;

    private const IMAGE_BUDGET_BYTES = 3_900_000;
    private const MAX_PRODUCTS = 220;
    private const JPEG_QUALITY = 44;

    public function generate(): array
    {
        $buckets = $this->bucketCandidates();
        $selected = $this->balancedSelection($buckets);

        if ($selected->isEmpty()) {
            throw new \RuntimeException('No active products with images and rates are available for the daily catalog.');
        }

        $output = $this->render($selected);
        $attempts = 0;

        while (strlen($output) > self::MAX_BYTES && $attempts < 12) {
            $attempts++;
            $removeCount = max(1, (int) ceil((strlen($output) - self::MAX_BYTES) / max(1, strlen($output) / $selected->count())) + 1);
            $selected = $this->removeOldestWithoutEmptyingBuckets($selected, $removeCount);
            $output = $this->render($selected);
        }

        if (strlen($output) > self::MAX_BYTES) {
            throw new \RuntimeException('Unable to reduce the daily catalog below 4.2 MB without emptying a price section.');
        }

        Storage::disk('local')->put(self::FILE_PATH, $output);

        $counts = $selected->countBy('bucket_key')->all();
        $meta = [
            'generated_at' => now()->toIso8601String(),
            'products_count' => $selected->count(),
            'bytes' => strlen($output),
            'size_mb' => round(strlen($output) / 1_000_000, 2),
            'bucket_counts' => $counts,
            'url' => route('daily-catalog.show'),
        ];

        AppSetting::put(self::META_KEY, $meta);

        return $meta;
    }

    public function metadata(): array
    {
        $stored = AppSetting::getArray(self::META_KEY, []);
        $exists = Storage::disk('local')->exists(self::FILE_PATH);

        return array_merge([
            'available' => $exists,
            'generated_at' => null,
            'products_count' => 0,
            'bytes' => 0,
            'size_mb' => 0,
            'bucket_counts' => [],
            'url' => $exists ? route('daily-catalog.show') : null,
        ], $stored, ['available' => $exists]);
    }

    private function bucketCandidates(): Collection
    {
        return collect($this->bucketDefinitions())->mapWithKeys(function (array $bucket) {
            $products = Product::query()
                ->where('is_active', true)
                ->where('status', 'active')
                ->where('price', '>', 0)
                ->whereHas('images')
                ->when($bucket['min'] !== null, fn ($query) => $query->where('price', '>', $bucket['min']))
                ->when($bucket['max'] !== null, fn ($query) => $query->where('price', '<=', $bucket['max']))
                ->with(['coverImage', 'firstImage'])
                ->orderByRaw('COALESCE(published_at, created_at) DESC')
                ->orderByDesc('id')
                ->limit(self::MAX_PRODUCTS)
                ->get()
                ->map(fn (Product $product) => $this->prepareProduct($product, $bucket))
                ->filter()
                ->values();

            return [$bucket['key'] => ['definition' => $bucket, 'products' => $products]];
        });
    }

    private function balancedSelection(Collection $buckets): Collection
    {
        $selected = collect();
        $imageBytes = 0;
        $offset = 0;

        while ($selected->count() < self::MAX_PRODUCTS) {
            $added = false;

            foreach ($buckets as $bucket) {
                $product = $bucket['products']->get($offset);
                if (! $product) {
                    continue;
                }

                if ($selected->isNotEmpty() && ($imageBytes + $product['image_bytes']) > self::IMAGE_BUDGET_BYTES) {
                    continue;
                }

                $selected->push($product);
                $imageBytes += $product['image_bytes'];
                $added = true;

                if ($selected->count() >= self::MAX_PRODUCTS) {
                    break;
                }
            }

            if (! $added) {
                break;
            }

            $offset++;
        }

        return $selected;
    }

    private function prepareProduct(Product $product, array $bucket): ?array
    {
        $image = $product->coverImage ?: $product->firstImage;
        if (! $image) {
            return null;
        }

        $jpeg = $this->optimizedJpeg($image);
        if ($jpeg === null) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (float) $product->price,
            'published_at' => $product->published_at ?: $product->created_at,
            'url' => route('catalog.show', $product),
            'image_data' => 'data:image/jpeg;base64,'.base64_encode($jpeg),
            'image_bytes' => strlen($jpeg),
            'bucket_key' => $bucket['key'],
            'bucket_label' => $bucket['label'],
        ];
    }

    private function optimizedJpeg(ProductImage $image): ?string
    {
        $path = $image->medium_path ?: $image->thumb_path ?: $image->path;
        if (! $path || ! Storage::disk($image->disk)->exists($path)) {
            return null;
        }

        $raw = Storage::disk($image->disk)->get($path);
        $source = $raw ? @imagecreatefromstring($raw) : false;

        if (! $source) {
            return null;
        }

        $targetWidth = 360;
        $targetHeight = 450;
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($targetWidth / max(1, $sourceWidth), $targetHeight / max(1, $sourceHeight));
        $scaledWidth = (int) ceil($sourceWidth * $scale);
        $scaledHeight = (int) ceil($sourceHeight * $scale);
        $offsetX = (int) floor(($targetWidth - $scaledWidth) / 2);
        $offsetY = (int) floor(($targetHeight - $scaledHeight) / 2);
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $scaledWidth, $scaledHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return is_string($jpeg) ? $jpeg : null;
    }

    private function render(Collection $selected): string
    {
        $definitions = collect($this->bucketDefinitions())->keyBy('key');
        $sections = $selected
            ->groupBy('bucket_key')
            ->map(fn (Collection $products, string $key) => [
                'key' => $key,
                'label' => $definitions->get($key)['label'],
                'products' => $products->values(),
            ])
            ->sortBy(fn (array $section) => array_search($section['key'], $definitions->keys()->all(), true))
            ->values();

        return Pdf::loadView('pdf.daily-catalog', [
            'sections' => $sections,
            'settings' => StorefrontSettingsController::shopDetails(),
            'generatedAt' => now(),
            'productsCount' => $selected->count(),
            'logoData' => $this->brandLogoData(),
        ])->setPaper('a4')->output();
    }

    private function brandLogoData(): ?string
    {
        $path = public_path('assets/brand/scak.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    private function removeOldestWithoutEmptyingBuckets(Collection $selected, int $count): Collection
    {
        $remaining = $selected->values();

        while ($count > 0) {
            $counts = $remaining->countBy('bucket_key');
            $index = false;
            for ($candidate = $remaining->count() - 1; $candidate >= 0; $candidate--) {
                $product = $remaining->get($candidate);
                if (($counts[$product['bucket_key']] ?? 0) > 1) {
                    $index = $candidate;
                    break;
                }
            }
            if ($index === false) {
                break;
            }

            $remaining->forget($index);
            $remaining = $remaining->values();
            $count--;
        }

        return $remaining;
    }

    private function bucketDefinitions(): array
    {
        return [
            ['key' => 'upto-300', 'label' => 'Up to Rs.300', 'min' => null, 'max' => 300],
            ['key' => '301-500', 'label' => 'Rs.301 - Rs.500', 'min' => 300, 'max' => 500],
            ['key' => '501-700', 'label' => 'Rs.501 - Rs.700', 'min' => 500, 'max' => 700],
            ['key' => '701-1000', 'label' => 'Rs.701 - Rs.1,000', 'min' => 700, 'max' => 1000],
            ['key' => '1001-1500', 'label' => 'Rs.1,001 - Rs.1,500', 'min' => 1000, 'max' => 1500],
            ['key' => 'above-1500', 'label' => 'Above Rs.1,500', 'min' => 1500, 'max' => null],
        ];
    }
}
