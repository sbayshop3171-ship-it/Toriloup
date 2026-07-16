<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_subscription_id',
        'tenant_id',
        'invoice_no',
        'status',
        'currency_code',
        'subtotal_amount',
        'transaction_fee_amount',
        'total_amount',
        'period_starts_at',
        'period_ends_at',
        'issued_at',
        'due_at',
        'paid_at',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_subscription_id' => 'integer',
        'tenant_id' => 'integer',
        'invoice_no' => 'string',
        'status' => 'string',
        'currency_code' => 'string',
        'subtotal_amount' => 'decimal:2',
        'transaction_fee_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'period_starts_at' => 'datetime',
        'period_ends_at' => 'datetime',
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'tenant_subscription_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
