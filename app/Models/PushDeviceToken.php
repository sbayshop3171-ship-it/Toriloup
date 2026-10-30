<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'installation_id',
        'platform',
        'token',
        'token_hash',
        'last_seen_at',
    ];

    protected $hidden = ['token', 'token_hash'];

    protected $casts = [
        'user_id' => 'integer',
        'installation_id' => 'string',
        'platform' => 'string',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
