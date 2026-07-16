<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'currency_code',
        'monthly_price',
        'yearly_price',
        'trial_days',
        'transaction_fee_type',
        'transaction_fee_value',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'status' => 'string',
        'currency_code' => 'string',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'trial_days' => 'integer',
        'transaction_fee_type' => 'string',
        'transaction_fee_value' => 'decimal:4',
        'metadata_json' => 'array',
    ];

    public function limits(): HasMany
    {
        return $this->hasMany(PlatformPlanLimit::class, 'plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
