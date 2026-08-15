<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupplierStockItemPhoto extends Model
{
    protected $fillable = ['supplier_stock_item_id', 'kind', 'disk', 'path', 'original_name', 'mime_type', 'bytes', 'is_current', 'uploaded_by'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(SupplierStockItem::class, 'supplier_stock_item_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
