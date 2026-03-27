<?php

namespace App\Services;

use App\Models\LegacyAnalyticsEvent;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\VisitorSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WordPressImportService
{
    public function __construct(private readonly ProductUpsertService $productUpsertService) {}

    public function import(array $options = []): array
    {
        $options = $this->normalizeOptions($options);

        if ($options['dry_run']) {
            return $this->buildDryRunSummary($options);
        }

        $summary = [
            'options' => $this->summarizeOptions($options),
        ];

        if ($options['import_users']) {
            $summary['admins'] = $this->importAdmins();
            $summary['customers'] = $this->importCustomers();
        }

        $this->importAttributeMasterData();
        $summary['products'] = $this->importProducts($options);

        if ($options['import_visitors']) {
            $summary['visitors'] = $this->importVisitors($options);
        }

        if ($options['import_analytics']) {
            $summary['analytics'] = $this->importAnalytics($options);
        }

        if ($options['archive_analytics']) {
            $summary['archives'] = $this->archiveAnalytics($options);
        }

        return $summary;
    }

    protected function importAdmins(): int
    {
        $connection = DB::connection('wordpress');
        $rows = $connection->table('otp_whitelisted_numbers')->get();
        $count = 0;

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row->phone);
            $existingUser = User::query()->where('phone', $phone)->first();

            if ($existingUser) {
                $existingUser->fill([
                    'name' => $row->description ?: $existingUser->name ?: 'SCAK Admin',
                    'approved_at' => $existingUser->approved_at ?? now(),
                    'phone_verified_at' => $existingUser->phone_verified_at ?? now(),
                    'is_active' => true,
                ]);

                if (! $existingUser->isAdmin()) {
                    $existingUser->role = User::ROLE_ADMIN;
                }

                $existingUser->save();
            } else {
                User::query()->create([
                    'name' => $row->description ?: 'SCAK Admin',
                    'phone' => $phone,
                    'role' => User::ROLE_ADMIN,
                    'approved_at' => now(),
                    'phone_verified_at' => now(),
                    'is_active' => true,
                ]);
            }

            $count++;
        }

        return $count;
    }

    protected function importCustomers(): int
    {
        $connection = DB::connection('wordpress');
        $rows = $connection->table('otp_verifications')
            ->where('verified', 1)
            ->select('name', 'phone', 'city', 'verified_at')
            ->distinct()
            ->get();

        $count = 0;

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row->phone);
            $existingUser = User::query()->where('phone', $phone)->first();

            if ($existingUser) {
                $existingUser->fill([
                    'name' => $row->name ?: $existingUser->name ?: 'SCAK Customer',
                    'city' => $row->city ?: $existingUser->city,
                    'phone_verified_at' => $existingUser->phone_verified_at ?? $row->verified_at ?? now(),
                    'is_active' => true,
                ]);

                if (! $existingUser->isAdmin()) {
                    $existingUser->role = User::ROLE_CUSTOMER;
                }

                $existingUser->save();
            } else {
                User::query()->create([
                    'name' => $row->name ?: 'SCAK Customer',
                    'phone' => $phone,
                    'city' => $row->city,
                    'role' => User::ROLE_CUSTOMER,
                    'phone_verified_at' => $row->verified_at ?: now(),
                    'is_active' => true,
                ]);
            }

            $count++;
        }

        return $count;
    }

    protected function importProducts(array $options): array
    {
        $posts = $this->getProductPosts($options);
        $summary = [
            'selected' => $posts->count(),
            'imported' => 0,
            'updated' => 0,
            'images_imported' => 0,
            'missing_images' => 0,
        ];

        $connection = DB::connection('wordpress');

        foreach ($posts as $post) {
            $meta = $connection->table('postmeta')
                ->where('post_id', $post->ID)
                ->pluck('meta_value', 'meta_key');

            $attributes = $this->parseWooAttributes($meta['_product_attributes'] ?? null);
            $legacySku = filled($meta['_sku'] ?? null) ? (string) $meta['_sku'] : null;
            $slug = Str::slug($post->post_title);
            $existingProductQuery = Product::query()->where('legacy_wordpress_id', $post->ID);

            if ($legacySku) {
                $existingProductQuery->orWhere(function ($query) use ($legacySku) {
                    $query->where('is_legacy_import', true)
                        ->where('legacy_wordpress_sku', $legacySku);
                });
            }

            $existingProduct = $existingProductQuery
                ->orWhere(function ($query) use ($slug) {
                    $query->where('is_legacy_import', true)
                        ->where('slug', $slug);
                })
                ->first();

            $product = $this->productUpsertService->upsert([
                'name' => $post->post_title,
                'price' => (float) ($meta['_price'] ?? $meta['_regular_price'] ?? 0),
                'supplier' => $this->attributeFirstValue($attributes, 'supplier', 'Unknown Supplier'),
                'city' => $this->attributeFirstValue($attributes, 'supplier_city', 'Unknown City'),
                'category' => $this->attributeFirstValue($attributes, 'category', 'General'),
                'top_fabric' => $this->attributeFirstValue($attributes, 'top_fabric'),
                'dupatta_fabric' => $this->attributeFirstValue($attributes, 'dupatta_fabric'),
                'description' => $post->post_content,
                'sizes' => $this->attributeValues($attributes, 'sizes'),
                'features' => $this->attributeValues($attributes, 'special_features'),
                'tags' => $this->buildLegacyTags($attributes),
                'status' => $post->post_status === 'publish' ? 'active' : 'archived',
                'published_at' => $post->post_date ?: null,
            ], $existingProduct);

            $product->forceFill([
                'is_legacy_import' => true,
                'legacy_wordpress_id' => $post->ID,
                'legacy_wordpress_sku' => $legacySku,
                'legacy_published_at' => $post->post_date ?: null,
                'legacy_modified_at' => $post->post_modified ?: null,
                'legacy_imported_at' => now(),
            ])->save();

            $imageSummary = $this->importProductImages($product, $meta, $connection);
            $summary['images_imported'] += $imageSummary['imported'];
            $summary['missing_images'] += $imageSummary['missing'];

            if ($existingProduct) {
                $summary['updated']++;
            } else {
                $summary['imported']++;
            }
        }

        return $summary;
    }

    protected function importAttributeMasterData(): void
    {
        $connection = DB::connection('wordpress');
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
            $raw = $connection->table('options')->where('option_name', $optionName)->value('option_value');
            $values = collect($this->maybeUnserialize($raw))->filter()->map(fn ($value) => trim((string) $value))->unique();

            foreach ($values as $value) {
                $modelClass::query()->firstOrCreate(
                    ['slug' => Str::slug($value)],
                    array_merge(['name' => $value], $extra),
                );
            }
        }
    }

    protected function importProductImages(Product $product, Collection $meta, ConnectionInterface $connection): array
    {
        $summary = ['imported' => 0, 'missing' => 0];
        $attachmentIds = collect();

        if (filled($meta['_thumbnail_id'] ?? null)) {
            $attachmentIds->push((int) $meta['_thumbnail_id']);
        }

        if (filled($meta['_product_image_gallery'] ?? null)) {
            $attachmentIds = $attachmentIds->merge(
                collect(explode(',', (string) $meta['_product_image_gallery']))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
            );
        }

        if ($attachmentIds->isEmpty()) {
            return $summary;
        }

        $uploadsPath = config('scak.wordpress.uploads_path');
        $disk = config('scak.storage.disk', 'products');

        foreach ($attachmentIds->unique()->values() as $index => $attachmentId) {
            if ($product->images()->where('legacy_wordpress_attachment_id', $attachmentId)->exists()) {
                continue;
            }

            $attachedFile = $connection->table('postmeta')
                ->where('post_id', $attachmentId)
                ->where('meta_key', '_wp_attached_file')
                ->value('meta_value');

            if (! $uploadsPath || ! $attachedFile) {
                $summary['missing']++;
                continue;
            }

            $fullPath = rtrim($uploadsPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $attachedFile);

            if (! is_file($fullPath)) {
                $summary['missing']++;
                continue;
            }

            $raw = @file_get_contents($fullPath);

            if ($raw === false) {
                $summary['missing']++;
                continue;
            }

            $targetPath = $this->productUpsertService->storeLegacyProductImage($product, $raw, basename($fullPath));

            ProductImage::query()->create([
                'product_id' => $product->id,
                'disk' => $disk,
                'path' => $targetPath,
                'original_name' => basename($fullPath),
                'sort_order' => $index + 1,
                'is_cover' => $index === 0,
                'legacy_wordpress_attachment_id' => $attachmentId,
            ]);

            $summary['imported']++;
        }

        return $summary;
    }

    protected function importVisitors(array $options): array
    {
        $connection = DB::connection('wordpress');
        $rows = $connection->table('otp_visitors')
            ->when($options['window_start'], fn ($query, $windowStart) => $query->where('visit_start', '>=', $windowStart))
            ->orderBy('id')
            ->get();

        $summary = [
            'selected' => $rows->count(),
            'imported' => 0,
            'updated' => 0,
        ];

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row->phone);
            $user = $phone ? User::query()->where('phone', $phone)->first() : null;
            $existing = VisitorSession::query()->where('legacy_wordpress_id', $row->id)->first();

            VisitorSession::query()->updateOrCreate(
                ['legacy_wordpress_id' => $row->id],
                [
                    'user_id' => $user?->id,
                    'phone' => $phone,
                    'customer_name' => $user?->name,
                    'customer_city' => $user?->city,
                    'session_key' => (string) ($row->session_id ?: 'wp-visitor-'.$row->id),
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent,
                    'device_type' => $this->normalizeNullableString($row->device_type),
                    'browser' => $this->normalizeNullableString($row->browser),
                    'os' => $this->normalizeNullableString($row->os),
                    'current_page' => $this->normalizePagePath($row->page_url),
                    'entry_page' => $this->normalizePagePath($row->page_url),
                    'referrer' => $this->normalizeNullableString($row->referrer),
                    'page_views' => max(1, (int) ($row->page_views ?? 1)),
                    'duration_seconds' => max(0, (int) ($row->duration_seconds ?? 0)),
                    'started_at' => $row->visit_start,
                    'last_activity_at' => $row->visit_end ?: $row->visit_start,
                    'is_legacy_import' => true,
                ],
            );

            if ($existing) {
                $summary['updated']++;
            } else {
                $summary['imported']++;
            }
        }

        return $summary;
    }

    protected function importAnalytics(array $options): array
    {
        $connection = DB::connection('wordpress');
        $rows = $connection->table('otp_analytics')
            ->when($options['window_start'], fn ($query, $windowStart) => $query->where('created_at', '>=', $windowStart))
            ->orderBy('id')
            ->get();

        $summary = [
            'selected' => $rows->count(),
            'imported' => 0,
            'updated' => 0,
        ];

        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row->phone);
            $user = $phone ? User::query()->where('phone', $phone)->first() : null;
            $existing = LegacyAnalyticsEvent::query()->where('legacy_wordpress_id', $row->id)->first();

            LegacyAnalyticsEvent::query()->updateOrCreate(
                ['legacy_wordpress_id' => $row->id],
                [
                    'user_id' => $user?->id,
                    'phone' => $phone,
                    'customer_name' => $user?->name,
                    'customer_city' => $user?->city,
                    'event_type' => $row->event_type,
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent,
                    'event_data' => $this->normalizeAnalyticsPayload($row->event_data),
                    'occurred_at' => $row->created_at,
                ],
            );

            if ($existing) {
                $summary['updated']++;
            } else {
                $summary['imported']++;
            }
        }

        return $summary;
    }

    protected function archiveAnalytics(array $options): array
    {
        $connection = DB::connection('wordpress');
        $basePath = trim((string) config('scak.wordpress.archive_path'), DIRECTORY_SEPARATOR);
        $suffix = $options['full_history'] ? 'full-history' : 'last-'.$options['days'].'-days';
        $analytics = $connection->table('otp_analytics')
            ->when($options['window_start'], fn ($query, $windowStart) => $query->where('created_at', '>=', $windowStart))
            ->get();
        $visitors = $connection->table('otp_visitors')
            ->when($options['window_start'], fn ($query, $windowStart) => $query->where('visit_start', '>=', $windowStart))
            ->get();

        $analyticsPath = $basePath.'/otp-analytics-'.$suffix.'.json';
        $visitorsPath = $basePath.'/otp-visitors-'.$suffix.'.json';

        Storage::disk('local')->put($analyticsPath, $analytics->toJson(JSON_PRETTY_PRINT));
        Storage::disk('local')->put($visitorsPath, $visitors->toJson(JSON_PRETTY_PRINT));

        return [
            'analytics' => Storage::disk('local')->path($analyticsPath),
            'visitors' => Storage::disk('local')->path($visitorsPath),
        ];
    }

    protected function buildDryRunSummary(array $options): array
    {
        $connection = DB::connection('wordpress');
        $posts = $this->getProductPosts($options);

        return [
            'options' => $this->summarizeOptions($options),
            'admins' => $options['import_users']
                ? (int) $connection->table('otp_whitelisted_numbers')->count()
                : 0,
            'customers' => $options['import_users']
                ? (int) $connection->table('otp_verifications')->where('verified', 1)->distinct('phone')->count('phone')
                : 0,
            'products' => [
                'selected' => $posts->count(),
                'image_attachments' => $this->countProductAttachments($posts, $connection),
                'uploads_path' => config('scak.wordpress.uploads_path'),
                'uploads_path_exists' => is_dir((string) config('scak.wordpress.uploads_path')),
            ],
            'visitors' => $options['import_visitors']
                ? (int) $connection->table('otp_visitors')
                    ->when($options['window_start'], fn ($query, $windowStart) => $query->where('visit_start', '>=', $windowStart))
                    ->count()
                : 0,
            'analytics' => $options['import_analytics']
                ? (int) $connection->table('otp_analytics')
                    ->when($options['window_start'], fn ($query, $windowStart) => $query->where('created_at', '>=', $windowStart))
                    ->count()
                : 0,
        ];
    }

    protected function getProductPosts(array $options): Collection
    {
        $connection = DB::connection('wordpress');
        $dateColumn = $this->resolveWordPressDateColumn($options['basis']);

        return $connection->table('posts')
            ->where('post_type', 'product')
            ->whereIn('post_status', ['publish', 'draft', 'private'])
            ->when($options['window_start'], fn ($query, $windowStart) => $query->where($dateColumn, '>=', $windowStart))
            ->select('ID', 'post_title', 'post_content', 'post_status', 'post_date', 'post_modified')
            ->orderBy('post_modified')
            ->get();
    }

    protected function countProductAttachments(Collection $posts, ConnectionInterface $connection): int
    {
        if ($posts->isEmpty()) {
            return 0;
        }

        $count = 0;

        foreach ($posts as $post) {
            $meta = $connection->table('postmeta')
                ->where('post_id', $post->ID)
                ->pluck('meta_value', 'meta_key');

            $attachments = collect();

            if (filled($meta['_thumbnail_id'] ?? null)) {
                $attachments->push((int) $meta['_thumbnail_id']);
            }

            if (filled($meta['_product_image_gallery'] ?? null)) {
                $attachments = $attachments->merge(
                    collect(explode(',', (string) $meta['_product_image_gallery']))
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                );
            }

            $count += $attachments->unique()->count();
        }

        return $count;
    }

    protected function buildLegacyTags(array $attributes): array
    {
        return collect([
            ...$this->attributeValues($attributes, 'supplier'),
            ...$this->attributeValues($attributes, 'supplier_city'),
            ...$this->attributeValues($attributes, 'category'),
            ...$this->attributeValues($attributes, 'top_fabric'),
            ...$this->attributeValues($attributes, 'dupatta_fabric'),
            ...$this->attributeValues($attributes, 'sizes'),
            ...$this->attributeValues($attributes, 'special_features'),
        ])->filter()->unique()->values()->all();
    }

    protected function attributeFirstValue(array $attributes, string $key, ?string $default = null): ?string
    {
        $value = $attributes[$key] ?? null;

        if (is_array($value)) {
            return $value[0] ?? $default;
        }

        return $value ?: $default;
    }

    protected function attributeValues(array $attributes, string $key): array
    {
        $value = $attributes[$key] ?? null;

        if (blank($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    protected function resolveWordPressDateColumn(string $basis): string
    {
        return match ($basis) {
            'created', 'published' => 'post_date',
            default => 'post_modified',
        };
    }

    protected function summarizeOptions(array $options): array
    {
        return [
            'basis' => $options['basis'],
            'days' => $options['days'],
            'full_history' => $options['full_history'],
            'import_users' => $options['import_users'],
            'import_visitors' => $options['import_visitors'],
            'import_analytics' => $options['import_analytics'],
            'archive_analytics' => $options['archive_analytics'],
            'dry_run' => $options['dry_run'],
        ];
    }

    protected function normalizeOptions(array $options): array
    {
        $days = isset($options['days']) && $options['days'] !== null
            ? max(1, (int) $options['days'])
            : null;
        $fullHistory = (bool) ($options['full_history'] ?? false);

        if ($fullHistory) {
            $days = null;
        }

        return [
            'days' => $days,
            'basis' => in_array(($options['basis'] ?? 'modified'), ['modified', 'created', 'published'], true)
                ? (string) $options['basis']
                : 'modified',
            'full_history' => $fullHistory,
            'import_users' => (bool) ($options['import_users'] ?? true),
            'import_visitors' => (bool) ($options['import_visitors'] ?? false),
            'import_analytics' => (bool) ($options['import_analytics'] ?? false),
            'archive_analytics' => (bool) ($options['archive_analytics'] ?? false),
            'dry_run' => (bool) ($options['dry_run'] ?? false),
            'window_start' => $days ? CarbonImmutable::now()->subDays($days)->startOfDay() : null,
        ];
    }

    protected function normalizePhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (Str::startsWith((string) $phone, '+')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return (string) config('scak.otp.country_code', '+91').$digits;
        }

        if (strlen($digits) === 12 && Str::startsWith($digits, '91')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    protected function normalizePagePath(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = parse_url((string) $url, PHP_URL_PATH);
        $query = parse_url((string) $url, PHP_URL_QUERY);
        $normalizedPath = $path ?: '/';

        if ($query) {
            $normalizedPath .= '?'.$query;
        }

        return Str::limit($normalizedPath, 255, '');
    }

    protected function normalizeNullableString(?string $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        return trim((string) $value);
    }

    protected function normalizeAnalyticsPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => $payload];
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
