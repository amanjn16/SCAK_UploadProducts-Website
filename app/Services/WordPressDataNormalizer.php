<?php

namespace App\Services;

use Illuminate\Support\Str;

class WordPressDataNormalizer
{
    public function buildLegacyTags(array $attributes): array
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

    public function attributeFirstValue(array $attributes, string $key, ?string $default = null): ?string
    {
        $value = $attributes[$key] ?? null;

        if (is_array($value)) {
            return $value[0] ?? $default;
        }

        return $value ?: $default;
    }

    public function attributeValues(array $attributes, string $key): array
    {
        $value = $attributes[$key] ?? null;

        if (blank($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    public function resolveWordPressDateColumn(string $basis): string
    {
        return match ($basis) {
            'created', 'published' => 'post_date',
            default => 'post_modified',
        };
    }

    public function normalizePhone(?string $phone): ?string
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

    public function normalizePagePath(?string $url): ?string
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

    public function normalizeNullableString(?string $value, ?int $limit = null): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($limit) {
            return Str::limit($normalized, $limit, '');
        }

        return $normalized;
    }

    public function normalizeAnalyticsPayload(mixed $payload): array
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

    public function parseWooAttributes(mixed $rawAttributes): array
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

    public function maybeUnserialize(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $unserialized = @unserialize($value, ['allowed_classes' => false]);

        return $unserialized === false && $value !== 'b:0;' ? $value : $unserialized;
    }
}
