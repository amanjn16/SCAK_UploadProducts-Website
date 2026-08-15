<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    private const VERSION_KEY = 'catalog:version';

    public function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public function bump(): int
    {
        $next = $this->version() + 1;
        Cache::forever(self::VERSION_KEY, $next);

        return $next;
    }

    public function remember(string $namespace, array $parts, int $ttlSeconds, Closure $callback): mixed
    {
        $key = $this->key($namespace, $parts);

        return Cache::remember($key, now()->addSeconds($ttlSeconds), $callback);
    }

    public function key(string $namespace, array $parts = []): string
    {
        $serialized = json_encode($parts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sprintf(
            'catalog:v%s:%s:%s',
            $this->version(),
            $namespace,
            md5((string) $serialized),
        );
    }
}
