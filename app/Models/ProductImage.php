<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'medium_path',
        'thumb_path',
        'original_name',
        'mime_type',
        'bytes',
        'sort_order',
        'is_cover',
        'legacy_wordpress_attachment_id',
    ];

    protected $appends = ['url', 'medium_url', 'thumb_url'];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'bytes' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return route('media.products.show', $this);
    }

    public function getMediumUrlAttribute(): string
    {
        return route('media.products.show', ['image' => $this, 'variant' => 'medium']);
    }

    public function getThumbUrlAttribute(): string
    {
        return route('media.products.show', ['image' => $this, 'variant' => 'thumb']);
    }
}
