<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantNotificationPreference extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'event_key', 'enabled'];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'event_key' => 'string',
        'enabled' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
