<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'sku',
        'price',
        'supplier_id',
        'city_id',
        'category_id',
        'top_fabric_id',
        'dupatta_fabric_id',
        'description',
        'is_active',
        'status',
        'published_at',
    ];

    protected $appends = ['cover_image_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function topFabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class, 'top_fabric_id');
    }

    public function dupattaFabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class, 'dupatta_fabric_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderByDesc('is_cover');
    }

    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(Size::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('images')
            ? $this->images->sortBy('sort_order')->firstWhere('is_cover', true) ?? $this->images->sortBy('sort_order')->first()
            : $this->images()->where('is_cover', true)->first() ?? $this->images()->orderBy('sort_order')->first();

        return $image ? Storage::disk($image->disk)->url($image->path) : null;
    }
}
