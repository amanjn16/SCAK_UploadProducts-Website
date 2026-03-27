<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyAnalyticsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'legacy_wordpress_id',
        'user_id',
        'phone',
        'customer_name',
        'customer_city',
        'event_type',
        'ip_address',
        'user_agent',
        'event_data',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_data' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
