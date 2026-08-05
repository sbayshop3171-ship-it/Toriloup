<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'audience',
        'event_key',
        'title',
        'body',
        'target_type',
        'target_value',
        'route_url',
        'data',
        'read_at',
        'pushed_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'audience' => 'string',
        'event_key' => 'string',
        'title' => 'string',
        'body' => 'string',
        'target_type' => 'string',
        'target_value' => 'string',
        'route_url' => 'string',
        'data' => 'array',
        'read_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
