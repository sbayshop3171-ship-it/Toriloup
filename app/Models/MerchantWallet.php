<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantWallet extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'currency_code',
        'available_balance',
        'holding_balance',
        'pending_withdrawal_balance',
        'total_earned',
        'total_withdrawn',
        'total_fees',
        'total_refunded',
        'last_settled_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'currency_code' => 'string',
        'available_balance' => 'decimal:6',
        'holding_balance' => 'decimal:6',
        'pending_withdrawal_balance' => 'decimal:6',
        'total_earned' => 'decimal:6',
        'total_withdrawn' => 'decimal:6',
        'total_fees' => 'decimal:6',
        'total_refunded' => 'decimal:6',
        'last_settled_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(MerchantWalletTransaction::class, 'wallet_id');
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(MerchantWithdrawal::class, 'wallet_id');
    }
}
