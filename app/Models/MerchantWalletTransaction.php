<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantWalletTransaction extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'tenant_id',
        'order_id',
        'transaction_id',
        'withdrawal_id',
        'type',
        'direction',
        'status',
        'currency_code',
        'gross_amount',
        'fee_amount',
        'amount',
        'balance_after',
        'available_at',
        'processed_at',
        'description',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'wallet_id' => 'integer',
        'tenant_id' => 'integer',
        'order_id' => 'integer',
        'transaction_id' => 'integer',
        'withdrawal_id' => 'integer',
        'type' => 'string',
        'direction' => 'string',
        'status' => 'string',
        'currency_code' => 'string',
        'gross_amount' => 'decimal:6',
        'fee_amount' => 'decimal:6',
        'amount' => 'decimal:6',
        'balance_after' => 'decimal:6',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(MerchantWallet::class, 'wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(MerchantWithdrawal::class, 'withdrawal_id');
    }
}
