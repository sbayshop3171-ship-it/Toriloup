<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MerchantWithdrawal extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'request_no',
        'tenant_id',
        'wallet_id',
        'payout_method_id',
        'amount',
        'fee_amount',
        'net_amount',
        'currency_code',
        'status',
        'destination_json',
        'merchant_note',
        'admin_note',
        'payout_reference',
        'requested_by_user_id',
        'processed_by_user_id',
        'requested_at',
        'processed_at',
        'metadata_json',
    ];

    protected $casts = [
        'id' => 'integer',
        'tenant_id' => 'integer',
        'wallet_id' => 'integer',
        'payout_method_id' => 'integer',
        'amount' => 'decimal:6',
        'fee_amount' => 'decimal:6',
        'net_amount' => 'decimal:6',
        'currency_code' => 'string',
        'status' => 'string',
        'destination_json' => 'array',
        'requested_by_user_id' => 'integer',
        'processed_by_user_id' => 'integer',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'metadata_json' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(MerchantWallet::class, 'wallet_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(MerchantPayoutMethod::class, 'payout_method_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id')->withTrashed();
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id')->withTrashed();
    }

    public function ledgerTransaction(): HasOne
    {
        return $this->hasOne(MerchantWalletTransaction::class, 'withdrawal_id');
    }
}
