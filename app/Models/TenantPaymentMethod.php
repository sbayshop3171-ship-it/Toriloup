<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider_code',
        'display_name',
        'status',
        'checkout_label',
        'fee_type',
        'fee_value',
        'sort_order',
        'config_json',
    ];

    protected $casts = [
        'id'             => 'integer',
        'tenant_id'      => 'integer',
        'provider_code'  => 'string',
        'display_name'   => 'string',
        'status'         => 'boolean',
        'checkout_label' => 'string',
        'fee_type'       => 'string',
        'fee_value'      => 'decimal:4',
        'sort_order'     => 'integer',
        'config_json'    => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
