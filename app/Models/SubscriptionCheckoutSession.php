<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionCheckoutSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'tenant_subscription_invoice_id',
        'provider_code',
        'status',
        'session_token',
        'external_reference',
        'return_url',
        'cancel_url',
        'completed_at',
        'expires_at',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'tenant_subscription_id' => 'integer',
        'tenant_subscription_invoice_id' => 'integer',
        'provider_code' => 'string',
        'status' => 'string',
        'session_token' => 'string',
        'external_reference' => 'string',
        'return_url' => 'string',
        'cancel_url' => 'string',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantSubscriptionInvoice::class, 'tenant_subscription_invoice_id');
    }
}
