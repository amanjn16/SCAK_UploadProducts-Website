<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WordPressImportService
{
    public function __construct(private readonly ProductUpsertService $productUpsertService) {}

    public function import(bool $archiveAnalytics = false): array
    {
        $summary = [
            'admins' => $this->importAdmins(),
            'customers' => $this->importCustomers(),
            'products' => $this->importProducts(),
        ];

        $this->importAttributeMasterData();

        if ($archiveAnalytics) {
            $summary['archives'] = $this->archiveAnalytics();
        }

        return $summary;
    }

    protected function importAdmins(): int
    {
        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $rows = $connection->table($prefix.'otp_whitelisted_numbers')->get();
        $count = 0;

        foreach ($rows as $row) {
            User::query()->updateOrCreate(
                ['phone' => $row->phone],
                [
                    'name' => $row->description ?: 'SCAK Admin',
                    'role' => User::ROLE_ADMIN,
                    'approved_at' => now(),
                    'phone_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $count++;
        }

        return $count;
    }

    protected function importCustomers(): int
    {
        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $rows = $connection->table($prefix.'otp_verifications')
            ->where('verified', 1)
            ->select('name', 'phone', 'city', 'verified_at')
            ->distinct()
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            User::query()->updateOrCreate(
                ['phone' => $row->phone],
                [
                    'name' => $row->name ?: 'SCAK Customer',
                    'city' => $row->city,
                    'role' => User::ROLE_CUSTOMER,
                    'phone_verified_at' => $row->verified_at ?: now(),
                    'is_active' => true,
                ],
            );

            $count++;
        }

        return $count;
    }

    protected function importProducts(): int
    {
        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $posts = $connection->table($prefix.'posts')
            ->where('post_type', 'product')
            ->whereIn('post_status', ['publish', 'draft', 'private'])
            ->select('ID', 'post_title', 'post_content', 'post_status')
            ->get();

        $count = 0;

        foreach ($posts as $post) {
            $meta = $connection->table($prefix.'postmeta')
                ->where('post_id', $post->ID)
                ->pluck('meta_value', 'meta_key');

            $attributes = $this->parseWooAttributes($meta['_product_attributes'] ?? null);

            $existingProduct = Product::query()
                ->when(isset($meta['_sku']) && filled($meta['_sku']), fn ($query) => $query->orWhere('sku', $meta['_sku']))
                ->orWhere('slug', Str::slug($post->post_title))
                ->first();

            $product = $this->productUpsertService->upsert([
                'name' => $post->post_title,
                'sku' => $meta['_sku'] ?? null,
                'price' => (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0),
                'supplier' => is_array($attributes['supplier'] ?? null) ? ($attributes['supplier'][0] ?? 'Unknown Supplier') : ($attributes['supplier'] ?? 'Unknown Supplier'),
                'city' => is_array($attributes['supplier_city'] ?? null) ? ($attributes['supplier_city'][0] ?? 'Unknown City') : ($attributes['supplier_city'] ?? 'Unknown City'),
                'category' => is_array($attributes['category'] ?? null) ? ($attributes['category'][0] ?? 'General') : ($attributes['category'] ?? 'General'),
                'top_fabric' => is_array($attributes['top_fabric'] ?? null) ? ($attributes['top_fabric'][0] ?? null) : ($attributes['top_fabric'] ?? null),
                'dupatta_fabric' => is_array($attributes['dupatta_fabric'] ?? null) ? ($attributes['dupatta_fabric'][0] ?? null) : ($attributes['dupatta_fabric'] ?? null),
                'description' => $post->post_content,
                'sizes' => is_array($attributes['sizes'] ?? null) ? $attributes['sizes'] : [],
                'features' => is_array($attributes['special_features'] ?? null) ? $attributes['special_features'] : [],
                'status' => $post->post_status === 'publish' ? 'active' : 'archived',
            ], $existingProduct);

            $this->importProductImages($product, $meta);
            $count++;
        }

        return $count;
    }

    protected function importAttributeMasterData(): void
    {
        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $optionMap = [
            'product_attributes_suppliers' => [\App\Models\Supplier::class, []],
            'product_attributes_supplier_cities' => [\App\Models\City::class, []],
            'product_attributes_categories' => [\App\Models\Category::class, []],
            'product_attributes_top_fabrics' => [\App\Models\Fabric::class, ['type' => 'top']],
            'product_attributes_dupatta_fabrics' => [\App\Models\Fabric::class, ['type' => 'dupatta']],
            'product_attributes_available_sizes' => [\App\Models\Size::class, []],
            'product_attributes_special_features' => [\App\Models\Feature::class, []],
        ];

        foreach ($optionMap as $optionName => [$modelClass, $extra]) {
            $raw = $connection->table($prefix.'options')->where('option_name', $optionName)->value('option_value');
            $values = collect($this->maybeUnserialize($raw))->filter()->map(fn ($value) => trim((string) $value))->unique();

            foreach ($values as $value) {
                $modelClass::query()->firstOrCreate(
                    ['slug' => Str::slug($value)],
                    array_merge(['name' => $value], $extra),
                );
            }
        }
    }

    protected function importProductImages(Product $product, Collection $meta): void
    {
        if ($product->images()->exists()) {
            return;
        }

        $attachmentIds = collect();

        if (filled($meta['_thumbnail_id'] ?? null)) {
            $attachmentIds->push((int) $meta['_thumbnail_id']);
        }

        if (filled($meta['_product_image_gallery'] ?? null)) {
            $galleryIds = collect(explode(',', (string) $meta['_product_image_gallery']))
                ->filter()
                ->map(fn ($id) => (int) $id);

            $attachmentIds = $attachmentIds->merge($galleryIds);
        }

        if ($attachmentIds->isEmpty()) {
            return;
        }

        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $uploadsPath = config('scak.wordpress.uploads_path');
        $disk = config('scak.storage.disk', 'products');

        foreach ($attachmentIds->unique()->values() as $index => $attachmentId) {
            $attachedFile = $connection->table($prefix.'postmeta')
                ->where('post_id', $attachmentId)
                ->where('meta_key', '_wp_attached_file')
                ->value('meta_value');

            if (! $uploadsPath || ! $attachedFile) {
                continue;
            }

            $fullPath = rtrim($uploadsPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $attachedFile);

            if (! is_file($fullPath)) {
                continue;
            }

            $targetPath = 'products/'.$product->slug.'/legacy-'.basename($fullPath);
            Storage::disk($disk)->put($targetPath, file_get_contents($fullPath));

            ProductImage::query()->create([
                'product_id' => $product->id,
                'disk' => $disk,
                'path' => $targetPath,
                'original_name' => basename($fullPath),
                'sort_order' => $index + 1,
                'is_cover' => $index === 0,
            ]);
        }
    }

    protected function archiveAnalytics(): array
    {
        $connection = DB::connection('wordpress');
        $prefix = $connection->getTablePrefix();
        $basePath = trim((string) config('scak.wordpress.archive_path'), DIRECTORY_SEPARATOR);
        $analytics = $connection->table($prefix.'otp_analytics')->get();
        $visitors = $connection->table($prefix.'otp_visitors')->get();

        $analyticsPath = $basePath.'/otp-analytics-archive.json';
        $visitorsPath = $basePath.'/otp-visitors-archive.json';

        Storage::disk('local')->put($analyticsPath, $analytics->toJson(JSON_PRETTY_PRINT));
        Storage::disk('local')->put($visitorsPath, $visitors->toJson(JSON_PRETTY_PRINT));

        return [
            'analytics' => Storage::disk('local')->path($analyticsPath),
            'visitors' => Storage::disk('local')->path($visitorsPath),
        ];
    }

    protected function parseWooAttributes(mixed $rawAttributes): array
    {
        $attributes = [];
        $decoded = $this->maybeUnserialize($rawAttributes);

        if (! is_array($decoded)) {
            return $attributes;
        }

        foreach ($decoded as $key => $attribute) {
            $value = data_get($attribute, 'value');

            if (! filled($value)) {
                continue;
            }

            $normalizedKey = Str::of((string) $key)
                ->lower()
                ->replace('pa_', '')
                ->replace('-', '_')
                ->replace(' ', '_')
                ->toString();

            $values = collect(preg_split('/\s*\|\s*|,/', (string) $value) ?: [])
                ->map(fn ($item) => trim($item))
                ->filter()
                ->values()
                ->all();

            $attributes[$normalizedKey] = count($values) === 1 ? $values[0] : $values;
        }

        return $attributes;
    }

    protected function maybeUnserialize(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $unserialized = @unserialize($value, ['allowed_classes' => false]);

        return $unserialized === false && $value !== 'b:0;' ? $value : $unserialized;
    }
}
