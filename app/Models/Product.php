<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'is_legacy_import',
        'legacy_wordpress_id',
        'legacy_wordpress_sku',
        'legacy_published_at',
        'legacy_modified_at',
        'legacy_imported_at',
    ];

    protected $appends = ['cover_image_url', 'cover_image_thumb_url', 'cover_image_original_url'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_legacy_import' => 'boolean',
            'published_at' => 'datetime',
            'legacy_published_at' => 'datetime',
            'legacy_modified_at' => 'datetime',
            'legacy_imported_at' => 'datetime',
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

    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_cover', true)->orderBy('sort_order');
    }

    public function firstImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order')->orderBy('id');
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
        return $this->resolveCoverImage()?->medium_url ?? $this->resolveCoverImage()?->url;
    }

    public function getCoverImageThumbUrlAttribute(): ?string
    {
        return $this->resolveCoverImage()?->thumb_url ?? $this->resolveCoverImage()?->url;
    }

    public function getCoverImageOriginalUrlAttribute(): ?string
    {
        return $this->resolveCoverImage()?->url;
    }

    protected function resolveCoverImage(): ?ProductImage
    {
        $image = null;

        if ($this->relationLoaded('coverImage')) {
            $image = $this->getRelation('coverImage');
        }

        if (! $image && $this->relationLoaded('firstImage')) {
            $image = $this->getRelation('firstImage');
        }

        if (! $image && $this->relationLoaded('images')) {
            $image = $this->images->firstWhere('is_cover', true) ?? $this->images->sortBy('sort_order')->first();
        }

        return $image ?? $this->coverImage()->first() ?? $this->firstImage()->first();
    }
}
