<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'key',
        'value',
    ];

    public static function getArray(string $key, array $default = []): array
    {
        $value = static::get($key, $default);

        return is_array($value) ? $value : $default;
    }

    public static function putArray(string $key, array $value): self
    {
        return static::put($key, $value);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $raw = static::query()->where('key', $key)->value('value');

        if ($raw === null) {
            return $default;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $raw;
    }

    public static function put(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }
}
