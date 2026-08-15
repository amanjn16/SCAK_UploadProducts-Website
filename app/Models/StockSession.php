<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockSession extends Model
{
    protected $fillable = ['supplier_id', 'count_date', 'status', 'created_by', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['count_date' => 'date', 'submitted_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockSessionItem::class);
    }
}
