<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockSessionItem extends Model
{
    protected $fillable = ['stock_session_id', 'supplier_stock_item_id', 'previous_quantity', 'quantity', 'check_status', 'note', 'checked_by', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockSession::class, 'stock_session_id');
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(SupplierStockItem::class, 'supplier_stock_item_id');
    }
}
