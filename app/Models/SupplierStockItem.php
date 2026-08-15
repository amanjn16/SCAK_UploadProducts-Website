<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierStockItem extends Model
{
    protected $fillable = ['supplier_id', 'product_id', 'name', 'current_quantity', 'is_active', 'last_counted_at', 'last_counted_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_counted_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SupplierStockItemPhoto::class);
    }

    public function currentPhoto(): HasOne
    {
        return $this->hasOne(SupplierStockItemPhoto::class)->where('kind', 'bundle')->where('is_current', true)->latestOfMany();
    }
}
