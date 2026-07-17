<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSupportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'owner_user_id',
        'impersonated_user_id',
        'tenant_member_id',
        'status',
        'handoff_code',
        'reason',
        'merchant_token_id',
        'started_at',
        'consumed_at',
        'expires_at',
        'ended_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'owner_user_id' => 'integer',
        'impersonated_user_id' => 'integer',
        'tenant_member_id' => 'integer',
        'merchant_token_id' => 'integer',
        'started_at' => 'datetime',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function impersonatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'impersonated_user_id');
    }

    public function tenantMember(): BelongsTo
    {
        return $this->belongsTo(TenantMember::class, 'tenant_member_id');
    }
}
