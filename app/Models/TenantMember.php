<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role_id',
        'status',
        'invited_by_user_id',
        'joined_at',
        'last_seen_at',
    ];

    protected $casts = [
        'id'                 => 'integer',
        'tenant_id'          => 'integer',
        'user_id'            => 'integer',
        'role_id'            => 'integer',
        'status'             => 'string',
        'invited_by_user_id' => 'integer',
        'joined_at'          => 'datetime',
        'last_seen_at'       => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'role_id');
    }
}
