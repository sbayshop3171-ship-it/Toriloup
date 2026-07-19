<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_id',
        'payment_gateway_id',
        'tenant_payment_method_id',
        'gateway_slug',
        'status',
        'idempotency_key',
        'provider_transaction_id',
        'amount',
        'amount_verified',
        'currency_code',
        'currency_verified',
        'backend_validation_passed',
        'failure_reason',
        'provider_payload_json',
        'started_at',
        'verified_at',
        'finished_at',
    ];

    protected $casts = [
        'id'                        => 'integer',
        'tenant_id'                 => 'integer',
        'order_id'                  => 'integer',
        'payment_gateway_id'        => 'integer',
        'tenant_payment_method_id'  => 'integer',
        'amount'                    => 'decimal:6',
        'amount_verified'           => 'decimal:6',
        'backend_validation_passed' => 'boolean',
        'provider_payload_json'     => 'array',
        'started_at'                => 'datetime',
        'verified_at'               => 'datetime',
        'finished_at'               => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function tenantPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(TenantPaymentMethod::class);
    }
}
