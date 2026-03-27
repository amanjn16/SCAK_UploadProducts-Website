<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference_code',
        'customer_name',
        'customer_phone',
        'customer_city',
        'note',
        'internal_notes',
        'status',
        'is_archived',
        'contacted_at',
        'confirmed_at',
        'paid_offline_at',
        'dispatched_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_archived' => 'boolean',
            'contacted_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'paid_offline_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderRequestItem::class);
    }
}
