<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'plan_code_snapshot',
        'plan_name_snapshot',
        'status',
        'billing_interval',
        'currency_code',
        'price_amount',
        'trial_ends_at',
        'starts_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancel_at_period_end',
        'ended_at',
        'activated_by_user_id',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'plan_id' => 'integer',
        'plan_code_snapshot' => 'string',
        'plan_name_snapshot' => 'string',
        'status' => 'string',
        'billing_interval' => 'string',
        'currency_code' => 'string',
        'price_amount' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'ended_at' => 'datetime',
        'activated_by_user_id' => 'integer',
        'metadata_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantSubscriptionInvoice::class, 'tenant_subscription_id');
    }
}
