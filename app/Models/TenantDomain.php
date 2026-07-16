<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'hostname',
        'domain_type',
        'is_primary',
        'is_fallback',
        'ssl_status',
        'verification_status',
        'dns_provider',
        'cloudflare_zone_id',
        'cloudflare_hostname_id',
        'verification_token',
        'verified_at',
        'last_checked_at',
    ];

    protected $casts = [
        'id'                  => 'integer',
        'tenant_id'           => 'integer',
        'hostname'            => 'string',
        'domain_type'         => 'string',
        'is_primary'          => 'boolean',
        'is_fallback'         => 'boolean',
        'ssl_status'          => 'string',
        'verification_status' => 'string',
        'dns_provider'        => 'string',
        'cloudflare_zone_id'  => 'string',
        'cloudflare_hostname_id' => 'string',
        'verification_token'  => 'string',
        'verified_at'         => 'datetime',
        'last_checked_at'     => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(DomainVerificationLog::class);
    }
}
