<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'session_key',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'current_page',
        'entry_page',
        'referrer',
        'page_views',
        'duration_seconds',
        'started_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
