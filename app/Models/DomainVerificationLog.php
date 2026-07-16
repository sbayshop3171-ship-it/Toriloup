<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainVerificationLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'tenant_domain_id',
        'check_status',
        'check_type',
        'message',
        'payload_json',
        'checked_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_domain_id' => 'integer',
        'message' => 'string',
        'payload_json' => 'array',
        'checked_at' => 'datetime',
    ];

    public function tenantDomain(): BelongsTo
    {
        return $this->belongsTo(TenantDomain::class);
    }
}
