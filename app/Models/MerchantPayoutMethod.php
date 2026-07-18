<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantPayoutMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'instructions',
        'fields_json',
        'status',
        'min_amount',
        'max_amount',
        'fee_type',
        'fee_value',
        'sort_order',
    ];

    protected $casts = [
        'id' => 'integer',
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
        'instructions' => 'string',
        'fields_json' => 'array',
        'status' => 'boolean',
        'min_amount' => 'decimal:6',
        'max_amount' => 'decimal:6',
        'fee_type' => 'string',
        'fee_value' => 'decimal:6',
        'sort_order' => 'integer',
    ];

    public function withdrawals(): HasMany
    {
        return $this->hasMany(MerchantWithdrawal::class, 'payout_method_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
