<?php

namespace App\Services\Saas;

use App\Models\MerchantPayoutMethod;
use App\Models\MerchantWallet;
use App\Models\MerchantWalletTransaction;
use App\Models\MerchantWithdrawal;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Currency\CurrencyConversionService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MerchantWalletService
{
    public const TYPE_ORDER_PAYMENT = 'order_payment';
    public const TYPE_REFUND_ADJUSTMENT = 'refund_adjustment';
    public const TYPE_WITHDRAWAL_REQUEST = 'withdrawal_request';
    public const TYPE_WITHDRAWAL_REVERSAL = 'withdrawal_reversal';
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REVERSED = 'reversed';

    private array $cashGatewaySlugs = [
        'cashondelivery',
        'cash_on_delivery',
        'cash-on-delivery',
        'cod',
    ];

    public function __construct(
        private readonly PlatformAuditLogService $auditLogService,
        private readonly CurrencyConversionService $currencyConversionService,
    )
    {
    }

    public function walletForTenant(Tenant $tenant): MerchantWallet
    {
        $wallet = MerchantWallet::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['currency_code' => $this->currencyForTenant($tenant)]
        );

        $tenantCurrency = $this->currencyForTenant($tenant);

        if ($wallet->currency_code !== $tenantCurrency && $this->walletHasNoMoney($wallet)) {
            $wallet->forceFill(['currency_code' => $tenantCurrency])->save();
        }

        return $wallet;
    }

    public function summary(Tenant $tenant): array
    {
        $this->releaseEligibleHoldings($tenant);

        $wallet = $this->walletForTenant($tenant)->fresh();

        return [
            'wallet' => $this->serializeWallet($wallet),
            'period_totals' => $this->statement($tenant),
            'recent_transactions' => MerchantWalletTransaction::withoutGlobalScopes()
                ->with(['order:id,order_serial_no,total,status,payment_status', 'withdrawal:id,request_no,status'])
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (MerchantWalletTransaction $transaction): array => $this->serializeTransaction($transaction))
                ->values(),
            'recent_withdrawals' => MerchantWithdrawal::withoutGlobalScopes()
                ->with(['payoutMethod', 'requestedBy:id,name,email,phone'])
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (MerchantWithdrawal $withdrawal): array => $this->serializeWithdrawal($withdrawal))
                ->values(),
            'settings' => [
                'holding_days' => $this->holdingDays(),
                'min_withdrawal_amount' => $this->minimumWithdrawalAmount(),
            ],
        ];
    }

    public function creditOrderPayment(Order $order, Transaction $transaction, string $gatewaySlug): ?MerchantWalletTransaction
    {
        $gatewaySlug = Str::of($gatewaySlug)->lower()->replace([' ', '_'], ['-', '-'])->toString();

        if ($order->tenant_id === null || in_array($gatewaySlug, $this->cashGatewaySlugs, true)) {
            return null;
        }

        $tenant = Tenant::query()->find($order->tenant_id);

        if (!$tenant instanceof Tenant) {
            return null;
        }

        return DB::transaction(function () use ($tenant, $order, $transaction, $gatewaySlug): MerchantWalletTransaction {
            $wallet = $this->lockWallet($tenant);

            $existing = MerchantWalletTransaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('order_id', $order->id)
                ->where('transaction_id', $transaction->id)
                ->where('type', self::TYPE_ORDER_PAYMENT)
                ->first();

            if ($existing instanceof MerchantWalletTransaction) {
                return $existing;
            }

            $settlement = $this->settlementForOrderAmount(
                $order,
                $tenant,
                $wallet,
                (float) ($transaction->amount ?: $order->total),
                $this->paymentCurrencyForOrder($order)
            );
            $grossAmount = $settlement['wallet_amount'];
            $feeAmount = $this->calculateOrderFee($tenant, $grossAmount);
            $netAmount = max($this->roundMoney($grossAmount - $feeAmount), 0);
            $holdingDays = $this->holdingDays();
            $availableAt = now()->addDays($holdingDays);
            $status = $holdingDays > 0 ? self::STATUS_PENDING : self::STATUS_AVAILABLE;

            if ($status === self::STATUS_PENDING) {
                $wallet->holding_balance = $this->roundMoney((float) $wallet->holding_balance + $netAmount);
            } else {
                $wallet->available_balance = $this->roundMoney((float) $wallet->available_balance + $netAmount);
                $wallet->last_settled_at = now();
            }

            $wallet->total_earned = $this->roundMoney((float) $wallet->total_earned + $netAmount);
            $wallet->total_fees = $this->roundMoney((float) $wallet->total_fees + $feeAmount);
            $wallet->save();

            return MerchantWalletTransaction::withoutGlobalScopes()->create([
                'wallet_id' => $wallet->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'transaction_id' => $transaction->id,
                'type' => self::TYPE_ORDER_PAYMENT,
                'direction' => 'credit',
                'status' => $status,
                'currency_code' => $wallet->currency_code,
                'gross_amount' => $grossAmount,
                'fee_amount' => $feeAmount,
                'amount' => $netAmount,
                'balance_after' => (float) $wallet->available_balance,
                'available_at' => $availableAt,
                'processed_at' => $status === self::STATUS_AVAILABLE ? now() : null,
                'description' => 'Online payment credited to merchant wallet',
                'metadata_json' => [
                    'gateway_slug' => $gatewaySlug,
                    'order_serial_no' => $order->order_serial_no,
                    'holding_days' => $holdingDays,
                    'charge_currency_code' => $settlement['source_currency_code'],
                    'charge_amount' => $settlement['source_amount'],
                    'wallet_currency_code' => $settlement['wallet_currency_code'],
                    'wallet_gross_amount' => $settlement['wallet_amount'],
                    'wallet_exchange_rate' => $settlement['exchange_rate'],
                    'wallet_settlement_strategy' => $settlement['strategy'],
                    'order_base_currency_code' => $settlement['base_currency_code'],
                    'order_display_currency_code' => $settlement['display_currency_code'],
                    'order_display_exchange_rate' => $settlement['display_exchange_rate'],
                ],
            ]);
        });
    }

    public function releaseEligibleHoldings(Tenant $tenant): int
    {
        $transactions = MerchantWalletTransaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', self::TYPE_ORDER_PAYMENT)
            ->where('direction', 'credit')
            ->where('status', self::STATUS_PENDING)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($tenant, $transactions): int {
            $wallet = $this->lockWallet($tenant);
            $released = 0;

            foreach ($transactions as $transaction) {
                $amount = (float) $transaction->amount;
                $wallet->holding_balance = $this->roundMoney(max((float) $wallet->holding_balance - $amount, 0));
                $wallet->available_balance = $this->roundMoney((float) $wallet->available_balance + $amount);
                $wallet->last_settled_at = now();
                $wallet->save();

                $transaction->status = self::STATUS_AVAILABLE;
                $transaction->processed_at = now();
                $transaction->balance_after = $wallet->available_balance;
                $transaction->save();

                $released++;
            }

            return $released;
        });
    }

    public function releaseAllEligibleHoldings(): int
    {
        return Tenant::query()
            ->whereHas('orders')
            ->get()
            ->sum(fn (Tenant $tenant): int => $this->releaseEligibleHoldings($tenant));
    }

    /**
     * @param array{payout_method_id:int,amount:float,destination?:array<string,mixed>,merchant_note?:string|null} $payload
     */
    public function requestWithdrawal(Tenant $tenant, User $user, array $payload): MerchantWithdrawal
    {
        $this->releaseEligibleHoldings($tenant);

        $method = MerchantPayoutMethod::query()
            ->active()
            ->findOrFail((int) $payload['payout_method_id']);

        $amount = $this->roundMoney((float) $payload['amount']);
        $destination = $this->validateWithdrawalDestination($method, $payload['destination'] ?? []);
        $feeAmount = $this->calculatePayoutFee($method, $amount);
        $totalDebit = $this->roundMoney($amount + $feeAmount);

        if ($amount < $this->minimumWithdrawalAmount()) {
            throw new Exception('Withdrawal amount is below the platform minimum.', 422);
        }

        if ((float) $method->min_amount > 0 && $amount < (float) $method->min_amount) {
            throw new Exception('Withdrawal amount is below this payout method minimum.', 422);
        }

        if ($method->max_amount !== null && (float) $method->max_amount > 0 && $amount > (float) $method->max_amount) {
            throw new Exception('Withdrawal amount is above this payout method maximum.', 422);
        }

        return DB::transaction(function () use ($tenant, $user, $method, $payload, $amount, $feeAmount, $totalDebit, $destination): MerchantWithdrawal {
            $wallet = $this->lockWallet($tenant);

            if ((float) $wallet->available_balance < $totalDebit) {
                throw new Exception('Insufficient available wallet balance.', 422);
            }

            $wallet->available_balance = $this->roundMoney((float) $wallet->available_balance - $totalDebit);
            $wallet->pending_withdrawal_balance = $this->roundMoney((float) $wallet->pending_withdrawal_balance + $amount);
            $wallet->total_fees = $this->roundMoney((float) $wallet->total_fees + $feeAmount);
            $wallet->save();

            $withdrawal = MerchantWithdrawal::withoutGlobalScopes()->create([
                'uuid' => (string) Str::uuid(),
                'request_no' => $this->nextWithdrawalNo(),
                'tenant_id' => $tenant->id,
                'wallet_id' => $wallet->id,
                'payout_method_id' => $method->id,
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'net_amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'status' => 'pending',
                'destination_json' => $destination,
                'merchant_note' => $payload['merchant_note'] ?? null,
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
                'metadata_json' => [
                    'total_wallet_debit' => $totalDebit,
                    'payout_method_code' => $method->code,
                ],
            ]);

            MerchantWalletTransaction::withoutGlobalScopes()->create([
                'wallet_id' => $wallet->id,
                'tenant_id' => $tenant->id,
                'withdrawal_id' => $withdrawal->id,
                'type' => self::TYPE_WITHDRAWAL_REQUEST,
                'direction' => 'debit',
                'status' => self::STATUS_PENDING,
                'currency_code' => $wallet->currency_code,
                'gross_amount' => $amount,
                'fee_amount' => $feeAmount,
                'amount' => $totalDebit,
                'balance_after' => $wallet->available_balance,
                'processed_at' => now(),
                'description' => 'Merchant payout request reserved from wallet',
                'metadata_json' => [
                    'request_no' => $withdrawal->request_no,
                    'payout_method_code' => $method->code,
                ],
            ]);

            $this->auditLogService->log(
                'merchant.wallet.withdrawal_requested',
                MerchantWithdrawal::class,
                $withdrawal->id,
                [],
                ['amount' => $amount, 'fee_amount' => $feeAmount, 'method' => $method->code],
                actor: $user,
                tenant: $tenant,
                actorScope: 'merchant'
            );

            return $withdrawal->load(['payoutMethod', 'requestedBy:id,name,email,phone']);
        });
    }

    public function approveWithdrawal(MerchantWithdrawal $withdrawal, User $actor, ?string $reference = null, ?string $note = null): MerchantWithdrawal
    {
        if ($withdrawal->status !== 'pending') {
            throw new Exception('Only pending withdrawals can be approved.', 422);
        }

        return DB::transaction(function () use ($withdrawal, $actor, $reference, $note): MerchantWithdrawal {
            $withdrawal = MerchantWithdrawal::withoutGlobalScopes()
                ->with('tenant')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new Exception('Only pending withdrawals can be approved.', 422);
            }

            $wallet = $this->lockWallet($withdrawal->tenant);
            $wallet->pending_withdrawal_balance = $this->roundMoney(max((float) $wallet->pending_withdrawal_balance - (float) $withdrawal->amount, 0));
            $wallet->total_withdrawn = $this->roundMoney((float) $wallet->total_withdrawn + (float) $withdrawal->amount);
            $wallet->last_settled_at = now();
            $wallet->save();

            $withdrawal->status = 'approved';
            $withdrawal->processed_by_user_id = $actor->id;
            $withdrawal->processed_at = now();
            $withdrawal->payout_reference = $reference;
            $withdrawal->admin_note = $note;
            $withdrawal->save();

            MerchantWalletTransaction::withoutGlobalScopes()
                ->where('withdrawal_id', $withdrawal->id)
                ->where('type', self::TYPE_WITHDRAWAL_REQUEST)
                ->update([
                    'status' => self::STATUS_COMPLETED,
                    'processed_at' => now(),
                    'balance_after' => $wallet->available_balance,
                    'updated_at' => now(),
                ]);

            $this->auditLogService->log(
                'platform.wallet.withdrawal_approved',
                MerchantWithdrawal::class,
                $withdrawal->id,
                ['status' => 'pending'],
                ['status' => 'approved', 'reference' => $reference],
                actor: $actor,
                tenant: $withdrawal->tenant,
                actorScope: 'platform'
            );

            return $withdrawal->load(['tenant:id,name,slug', 'payoutMethod', 'requestedBy:id,name,email,phone']);
        });
    }

    public function rejectWithdrawal(MerchantWithdrawal $withdrawal, User $actor, string $reason): MerchantWithdrawal
    {
        if ($withdrawal->status !== 'pending') {
            throw new Exception('Only pending withdrawals can be rejected.', 422);
        }

        return DB::transaction(function () use ($withdrawal, $actor, $reason): MerchantWithdrawal {
            $withdrawal = MerchantWithdrawal::withoutGlobalScopes()
                ->with('tenant')
                ->whereKey($withdrawal->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($withdrawal->status !== 'pending') {
                throw new Exception('Only pending withdrawals can be rejected.', 422);
            }

            $wallet = $this->lockWallet($withdrawal->tenant);
            $walletDebit = $this->roundMoney((float) $withdrawal->amount + (float) $withdrawal->fee_amount);
            $wallet->available_balance = $this->roundMoney((float) $wallet->available_balance + $walletDebit);
            $wallet->pending_withdrawal_balance = $this->roundMoney(max((float) $wallet->pending_withdrawal_balance - (float) $withdrawal->amount, 0));
            $wallet->total_fees = $this->roundMoney(max((float) $wallet->total_fees - (float) $withdrawal->fee_amount, 0));
            $wallet->save();

            $withdrawal->status = 'rejected';
            $withdrawal->processed_by_user_id = $actor->id;
            $withdrawal->processed_at = now();
            $withdrawal->admin_note = $reason;
            $withdrawal->save();

            MerchantWalletTransaction::withoutGlobalScopes()
                ->where('withdrawal_id', $withdrawal->id)
                ->where('type', self::TYPE_WITHDRAWAL_REQUEST)
                ->update([
                    'status' => self::STATUS_REVERSED,
                    'processed_at' => now(),
                    'balance_after' => $wallet->available_balance,
                    'updated_at' => now(),
                ]);

            MerchantWalletTransaction::withoutGlobalScopes()->create([
                'wallet_id' => $wallet->id,
                'tenant_id' => $withdrawal->tenant_id,
                'withdrawal_id' => $withdrawal->id,
                'type' => self::TYPE_WITHDRAWAL_REVERSAL,
                'direction' => 'credit',
                'status' => self::STATUS_AVAILABLE,
                'currency_code' => $wallet->currency_code,
                'gross_amount' => $walletDebit,
                'fee_amount' => 0,
                'amount' => $walletDebit,
                'balance_after' => $wallet->available_balance,
                'processed_at' => now(),
                'description' => 'Rejected withdrawal returned to available balance',
                'metadata_json' => [
                    'request_no' => $withdrawal->request_no,
                    'reason' => $reason,
                ],
            ]);

            $this->auditLogService->log(
                'platform.wallet.withdrawal_rejected',
                MerchantWithdrawal::class,
                $withdrawal->id,
                ['status' => 'pending'],
                ['status' => 'rejected', 'reason' => $reason],
                actor: $actor,
                tenant: $withdrawal->tenant,
                actorScope: 'platform'
            );

            return $withdrawal->load(['tenant:id,name,slug', 'payoutMethod', 'requestedBy:id,name,email,phone']);
        });
    }

    public function reverseOrderPayment(Order $order, ?float $amount = null, string $reason = 'refund', ?int $returnAndRefundId = null): ?MerchantWalletTransaction
    {
        if ($order->tenant_id === null) {
            return null;
        }

        $tenant = Tenant::query()->find($order->tenant_id);

        if (!$tenant instanceof Tenant) {
            return null;
        }

        return DB::transaction(function () use ($tenant, $order, $amount, $reason, $returnAndRefundId): ?MerchantWalletTransaction {
            $wallet = $this->lockWallet($tenant);

            $originalCredit = MerchantWalletTransaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('order_id', $order->id)
                ->where('type', self::TYPE_ORDER_PAYMENT)
                ->whereIn('status', [self::STATUS_PENDING, self::STATUS_AVAILABLE, self::STATUS_COMPLETED])
                ->first();

            if (!$originalCredit instanceof MerchantWalletTransaction) {
                return null;
            }

            $existingAdjustment = MerchantWalletTransaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('order_id', $order->id)
                ->where('type', self::TYPE_REFUND_ADJUSTMENT)
                ->first();

            if ($existingAdjustment instanceof MerchantWalletTransaction) {
                return $existingAdjustment;
            }

            $settlement = $this->settlementForOrderAmount(
                $order,
                $tenant,
                $wallet,
                (float) ($amount ?? $order->total),
                $this->paymentCurrencyForOrder($order)
            );
            $refundAmount = $settlement['wallet_amount'];

            if ($refundAmount <= 0) {
                return null;
            }

            $remaining = $refundAmount;
            $holdingDebit = min((float) $wallet->holding_balance, $remaining);
            $wallet->holding_balance = $this->roundMoney((float) $wallet->holding_balance - $holdingDebit);
            $remaining = $this->roundMoney($remaining - $holdingDebit);
            $wallet->available_balance = $this->roundMoney((float) $wallet->available_balance - $remaining);
            $wallet->total_refunded = $this->roundMoney((float) $wallet->total_refunded + $refundAmount);
            $wallet->save();

            return MerchantWalletTransaction::withoutGlobalScopes()->create([
                'wallet_id' => $wallet->id,
                'tenant_id' => $tenant->id,
                'order_id' => $order->id,
                'type' => self::TYPE_REFUND_ADJUSTMENT,
                'direction' => 'debit',
                'status' => self::STATUS_COMPLETED,
                'currency_code' => $wallet->currency_code,
                'gross_amount' => $refundAmount,
                'fee_amount' => 0,
                'amount' => $refundAmount,
                'balance_after' => $wallet->available_balance,
                'processed_at' => now(),
                'description' => 'Refund or chargeback adjusted from merchant wallet',
                'metadata_json' => [
                    'reason' => $reason,
                    'return_and_refund_id' => $returnAndRefundId,
                    'holding_debit' => $holdingDebit,
                    'available_debit' => $remaining,
                    'charge_currency_code' => $settlement['source_currency_code'],
                    'charge_amount' => $settlement['source_amount'],
                    'wallet_currency_code' => $settlement['wallet_currency_code'],
                    'wallet_refund_amount' => $settlement['wallet_amount'],
                    'wallet_exchange_rate' => $settlement['exchange_rate'],
                    'wallet_settlement_strategy' => $settlement['strategy'],
                    'order_base_currency_code' => $settlement['base_currency_code'],
                    'order_display_currency_code' => $settlement['display_currency_code'],
                    'order_display_exchange_rate' => $settlement['display_exchange_rate'],
                ],
            ]);
        });
    }

    public function payoutMethods(bool $activeOnly = false): Collection
    {
        return MerchantPayoutMethod::query()
            ->when($activeOnly, fn (Builder $query): Builder => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MerchantPayoutMethod $method): array => $this->serializePayoutMethod($method));
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function upsertPayoutMethod(array $payload): MerchantPayoutMethod
    {
        $code = Str::of($payload['code'] ?? $payload['name'] ?? '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();

        if ($code === '') {
            throw new Exception('Payout method code is required.', 422);
        }

        return MerchantPayoutMethod::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $payload['name'],
                'description' => $payload['description'] ?? null,
                'instructions' => $payload['instructions'] ?? null,
                'fields_json' => $this->normalizePayoutFields($payload['fields'] ?? $payload['fields_json'] ?? []),
                'status' => (bool) ($payload['status'] ?? true),
                'min_amount' => $this->roundMoney((float) ($payload['min_amount'] ?? 0)),
                'max_amount' => filled($payload['max_amount'] ?? null) ? $this->roundMoney((float) $payload['max_amount']) : null,
                'fee_type' => $payload['fee_type'] ?? 'none',
                'fee_value' => $this->roundMoney((float) ($payload['fee_value'] ?? 0)),
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
            ]
        );
    }

    public function statement(Tenant $tenant, ?string $fromDate = null, ?string $toDate = null): array
    {
        $query = MerchantWalletTransaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->when($fromDate, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $fromDate))
            ->when($toDate, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $toDate));

        $transactions = $query->get();

        return $this->aggregateTransactions($transactions);
    }

    public function platformOverview(): array
    {
        $this->releaseAllEligibleHoldings();

        $wallets = MerchantWallet::withoutGlobalScopes()->get();
        $withdrawals = MerchantWithdrawal::withoutGlobalScopes()->get();

        return [
            'wallets_count' => $wallets->count(),
            'available_balance' => $this->roundMoney((float) $wallets->sum('available_balance')),
            'holding_balance' => $this->roundMoney((float) $wallets->sum('holding_balance')),
            'pending_withdrawal_balance' => $this->roundMoney((float) $wallets->sum('pending_withdrawal_balance')),
            'total_earned' => $this->roundMoney((float) $wallets->sum('total_earned')),
            'total_withdrawn' => $this->roundMoney((float) $wallets->sum('total_withdrawn')),
            'total_fees' => $this->roundMoney((float) $wallets->sum('total_fees')),
            'total_refunded' => $this->roundMoney((float) $wallets->sum('total_refunded')),
            'withdrawals_pending_count' => $withdrawals->where('status', 'pending')->count(),
            'withdrawals_pending_amount' => $this->roundMoney((float) $withdrawals->where('status', 'pending')->sum('amount')),
            'withdrawals_approved_count' => $withdrawals->where('status', 'approved')->count(),
        ];
    }

    public function serializeWallet(MerchantWallet $wallet): array
    {
        return [
            'id' => $wallet->id,
            'tenant_id' => $wallet->tenant_id,
            'currency_code' => $wallet->currency_code,
            'available_balance' => (float) $wallet->available_balance,
            'holding_balance' => (float) $wallet->holding_balance,
            'pending_withdrawal_balance' => (float) $wallet->pending_withdrawal_balance,
            'total_earned' => (float) $wallet->total_earned,
            'total_withdrawn' => (float) $wallet->total_withdrawn,
            'total_fees' => (float) $wallet->total_fees,
            'total_refunded' => (float) $wallet->total_refunded,
            'last_settled_at' => $wallet->last_settled_at?->toISOString(),
            'created_at' => $wallet->created_at?->toISOString(),
            'updated_at' => $wallet->updated_at?->toISOString(),
        ];
    }

    public function serializeTransaction(MerchantWalletTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'tenant_id' => $transaction->tenant_id,
            'order_id' => $transaction->order_id,
            'transaction_id' => $transaction->transaction_id,
            'withdrawal_id' => $transaction->withdrawal_id,
            'type' => $transaction->type,
            'direction' => $transaction->direction,
            'status' => $transaction->status,
            'currency_code' => $transaction->currency_code,
            'gross_amount' => (float) $transaction->gross_amount,
            'fee_amount' => (float) $transaction->fee_amount,
            'amount' => (float) $transaction->amount,
            'balance_after' => (float) $transaction->balance_after,
            'description' => $transaction->description,
            'metadata' => $transaction->metadata_json ?? [],
            'available_at' => $transaction->available_at?->toISOString(),
            'processed_at' => $transaction->processed_at?->toISOString(),
            'created_at' => $transaction->created_at?->toISOString(),
            'order' => $transaction->relationLoaded('order') && $transaction->order
                ? $transaction->order->only(['id', 'order_serial_no', 'total', 'status', 'payment_status'])
                : null,
            'withdrawal' => $transaction->relationLoaded('withdrawal') && $transaction->withdrawal
                ? $transaction->withdrawal->only(['id', 'request_no', 'status'])
                : null,
        ];
    }

    public function serializeWithdrawal(MerchantWithdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'uuid' => $withdrawal->uuid,
            'request_no' => $withdrawal->request_no,
            'tenant_id' => $withdrawal->tenant_id,
            'wallet_id' => $withdrawal->wallet_id,
            'payout_method_id' => $withdrawal->payout_method_id,
            'amount' => (float) $withdrawal->amount,
            'fee_amount' => (float) $withdrawal->fee_amount,
            'net_amount' => (float) $withdrawal->net_amount,
            'currency_code' => $withdrawal->currency_code,
            'status' => $withdrawal->status,
            'destination' => $withdrawal->destination_json ?? [],
            'destination_details' => $this->destinationDetails($withdrawal),
            'merchant_note' => $withdrawal->merchant_note,
            'admin_note' => $withdrawal->admin_note,
            'payout_reference' => $withdrawal->payout_reference,
            'requested_at' => $withdrawal->requested_at?->toISOString(),
            'processed_at' => $withdrawal->processed_at?->toISOString(),
            'created_at' => $withdrawal->created_at?->toISOString(),
            'requested_by' => $withdrawal->relationLoaded('requestedBy') && $withdrawal->requestedBy
                ? $withdrawal->requestedBy->only(['id', 'name', 'email', 'phone'])
                : null,
            'tenant' => $withdrawal->relationLoaded('tenant') && $withdrawal->tenant
                ? $withdrawal->tenant->only(['id', 'name', 'slug'])
                : null,
            'payout_method' => $withdrawal->relationLoaded('payoutMethod') && $withdrawal->payoutMethod
                ? $this->serializePayoutMethod($withdrawal->payoutMethod)
                : null,
        ];
    }

    public function serializePayoutMethod(MerchantPayoutMethod $method): array
    {
        return [
            'id' => $method->id,
            'code' => $method->code,
            'name' => $method->name,
            'description' => $method->description,
            'instructions' => $method->instructions,
            'fields' => $this->normalizePayoutFields($method->fields_json ?? []),
            'status' => (bool) $method->status,
            'min_amount' => (float) $method->min_amount,
            'max_amount' => $method->max_amount === null ? null : (float) $method->max_amount,
            'fee_type' => $method->fee_type,
            'fee_value' => (float) $method->fee_value,
            'sort_order' => $method->sort_order,
        ];
    }

    private function lockWallet(Tenant $tenant): MerchantWallet
    {
        $this->walletForTenant($tenant);

        return MerchantWallet::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function calculateOrderFee(Tenant $tenant, float $grossAmount): float
    {
        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active'])
            ->latest('id')
            ->first();

        $plan = $subscription?->plan;
        $feeType = $plan?->transaction_fee_type ?? 'none';
        $feeValue = (float) ($plan?->transaction_fee_value ?? 0);

        $fee = match ($feeType) {
            'fixed' => $feeValue,
            'percent' => $grossAmount * $feeValue / 100,
            default => 0,
        };

        return min($this->roundMoney($fee), $grossAmount);
    }

    private function calculatePayoutFee(MerchantPayoutMethod $method, float $amount): float
    {
        $fee = match ($method->fee_type) {
            'fixed' => (float) $method->fee_value,
            'percent' => $amount * (float) $method->fee_value / 100,
            default => 0,
        };

        return min($this->roundMoney($fee), $amount);
    }

    /**
     * @param array<int,array<string,mixed>>|array<string,mixed>|null $fields
     * @return array<int,array<string,mixed>>
     */
    private function normalizePayoutFields(?array $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $allowedTypes = ['text', 'email', 'number', 'url', 'textarea', 'select'];
        $allowedWidths = [25, 33, 50, 100];
        $normalizedFields = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            $key = trim((string) ($field['key'] ?? ''));

            if ($key === '' && $label !== '') {
                $key = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString();
            }

            $key = Str::of($key)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->toString();

            if ($key === '' || $label === '') {
                continue;
            }

            $type = strtolower((string) ($field['type'] ?? 'text'));
            $width = (int) ($field['width'] ?? 100);
            $options = collect($field['options'] ?? [])
                ->filter(fn ($option): bool => is_scalar($option) && trim((string) $option) !== '')
                ->map(fn ($option): string => trim((string) $option))
                ->values()
                ->all();

            $normalizedFields[] = [
                'key' => $key,
                'label' => $label,
                'type' => in_array($type, $allowedTypes, true) ? $type : 'text',
                'required' => (bool) ($field['required'] ?? true),
                'placeholder' => trim((string) ($field['placeholder'] ?? '')),
                'instructions' => trim((string) ($field['instructions'] ?? '')),
                'width' => in_array($width, $allowedWidths, true) ? $width : 100,
                'options' => $options,
            ];
        }

        return $normalizedFields;
    }

    /**
     * @param array<string,mixed> $destination
     * @return array<string,mixed>
     */
    private function validateWithdrawalDestination(MerchantPayoutMethod $method, array $destination): array
    {
        $fields = $this->normalizePayoutFields($method->fields_json ?? []);
        $cleanDestination = [];

        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $destination[$key] ?? null;
            $value = is_scalar($value) ? trim((string) $value) : $value;

            if (($field['required'] ?? false) && blank($value)) {
                throw new Exception($field['label'] . ' is required for ' . $method->name . ' withdrawal.', 422);
            }

            if (blank($value)) {
                continue;
            }

            if (($field['type'] ?? 'text') === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw new Exception($field['label'] . ' must be a valid email address.', 422);
            }

            if (($field['type'] ?? 'text') === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                throw new Exception($field['label'] . ' must be a valid URL.', 422);
            }

            if (($field['type'] ?? 'text') === 'number' && !is_numeric($value)) {
                throw new Exception($field['label'] . ' must be a number.', 422);
            }

            if (($field['type'] ?? 'text') === 'select' && !empty($field['options']) && !in_array((string) $value, $field['options'], true)) {
                throw new Exception($field['label'] . ' selection is not available.', 422);
            }

            $cleanDestination[$key] = $value;
        }

        foreach ($destination as $key => $value) {
            if (!is_string($key) || array_key_exists($key, $cleanDestination) || !is_scalar($value)) {
                continue;
            }

            $cleanDestination[$key] = trim((string) $value);
        }

        return $cleanDestination;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function destinationDetails(MerchantWithdrawal $withdrawal): array
    {
        $destination = $withdrawal->destination_json ?? [];
        $fields = $withdrawal->relationLoaded('payoutMethod') && $withdrawal->payoutMethod
            ? $this->normalizePayoutFields($withdrawal->payoutMethod->fields_json ?? [])
            : [];

        $details = [];

        foreach ($fields as $field) {
            $key = $field['key'];

            if (!array_key_exists($key, $destination)) {
                continue;
            }

            $details[] = [
                'key' => $key,
                'label' => $field['label'],
                'type' => $field['type'],
                'value' => $destination[$key],
            ];
        }

        foreach ($destination as $key => $value) {
            if (collect($details)->contains('key', $key)) {
                continue;
            }

            $details[] = [
                'key' => $key,
                'label' => Str::headline((string) $key),
                'type' => 'text',
                'value' => is_scalar($value) ? $value : json_encode($value),
            ];
        }

        return $details;
    }

    private function aggregateTransactions(Collection $transactions): array
    {
        $creditTransactions = $transactions->where('direction', 'credit');
        $debitTransactions = $transactions->where('direction', 'debit');

        return [
            'credits_count' => $creditTransactions->count(),
            'debits_count' => $debitTransactions->count(),
            'gross_sales' => $this->roundMoney((float) $transactions->where('type', self::TYPE_ORDER_PAYMENT)->sum('gross_amount')),
            'net_sales' => $this->roundMoney((float) $transactions->where('type', self::TYPE_ORDER_PAYMENT)->sum('amount')),
            'fees' => $this->roundMoney((float) $transactions->sum('fee_amount')),
            'refunds' => $this->roundMoney((float) $transactions->where('type', self::TYPE_REFUND_ADJUSTMENT)->sum('amount')),
            'withdrawal_requests' => $this->roundMoney((float) $transactions->where('type', self::TYPE_WITHDRAWAL_REQUEST)->sum('gross_amount')),
            'withdrawal_reversals' => $this->roundMoney((float) $transactions->where('type', self::TYPE_WITHDRAWAL_REVERSAL)->sum('amount')),
            'credits_total' => $this->roundMoney((float) $creditTransactions->sum('amount')),
            'debits_total' => $this->roundMoney((float) $debitTransactions->sum('amount')),
        ];
    }

    private function nextWithdrawalNo(): string
    {
        do {
            $requestNo = 'WD-' . now()->format('ymd') . '-' . Str::upper(Str::random(6));
        } while (MerchantWithdrawal::withoutGlobalScopes()->where('request_no', $requestNo)->exists());

        return $requestNo;
    }

    /**
     * @return array{source_amount: float, source_currency_code: string, wallet_amount: float, wallet_currency_code: string, exchange_rate: float, strategy: string, base_currency_code: string, display_currency_code: string, display_exchange_rate: float}
     */
    private function settlementForOrderAmount(
        Order $order,
        Tenant $tenant,
        MerchantWallet $wallet,
        float $sourceAmount,
        ?string $sourceCurrencyCode = null
    ): array {
        $sourceAmount = $this->roundMoney($sourceAmount);
        $walletCurrencyCode = $this->normalizeCurrencyCode($wallet->currency_code ?: $this->currencyForTenant($tenant));
        $sourceCurrencyCode = $this->normalizeCurrencyCode($sourceCurrencyCode ?: $this->paymentCurrencyForOrder($order));
        $baseCurrencyCode = $this->normalizeCurrencyCode($order->base_currency_code ?: $tenant->primary_currency_code ?: $walletCurrencyCode);
        $displayCurrencyCode = $this->normalizeCurrencyCode($order->display_currency_code ?: $order->charge_currency_code ?: $sourceCurrencyCode);
        $displayExchangeRate = (float) ($order->display_exchange_rate ?: 0);

        if ($sourceCurrencyCode === '') {
            $sourceCurrencyCode = $walletCurrencyCode;
        }

        if ($walletCurrencyCode === '') {
            $walletCurrencyCode = $sourceCurrencyCode;
        }

        $exchangeRate = 1.0;
        $strategy = 'same_currency';

        if ($sourceCurrencyCode !== $walletCurrencyCode) {
            if ($displayExchangeRate > 0 && $sourceCurrencyCode === $displayCurrencyCode && $walletCurrencyCode === $baseCurrencyCode) {
                $exchangeRate = round(1 / $displayExchangeRate, 8);
                $strategy = 'order_snapshot_inverse_display_rate';
            } elseif ($displayExchangeRate > 0 && $sourceCurrencyCode === $baseCurrencyCode && $walletCurrencyCode === $displayCurrencyCode) {
                $exchangeRate = round($displayExchangeRate, 8);
                $strategy = 'order_snapshot_display_rate';
            } else {
                $exchangeRate = $this->currencyConversionService->exchangeRateBetween($sourceCurrencyCode, $walletCurrencyCode, $tenant);
                $strategy = 'latest_catalog_rate';
            }
        }

        return [
            'source_amount' => $sourceAmount,
            'source_currency_code' => $sourceCurrencyCode,
            'wallet_amount' => $this->roundMoney($sourceAmount * $exchangeRate),
            'wallet_currency_code' => $walletCurrencyCode,
            'exchange_rate' => $exchangeRate,
            'strategy' => $strategy,
            'base_currency_code' => $baseCurrencyCode,
            'display_currency_code' => $displayCurrencyCode,
            'display_exchange_rate' => $displayExchangeRate,
        ];
    }

    private function paymentCurrencyForOrder(Order $order): string
    {
        return $this->normalizeCurrencyCode(
            $order->charge_currency_code
            ?: $order->display_currency_code
            ?: $order->base_currency_code
            ?: $order->tenant?->primary_currency_code
            ?: config('currency.base_code', 'USD')
        );
    }

    private function currencyForTenant(Tenant $tenant): string
    {
        return $this->normalizeCurrencyCode($tenant->primary_currency_code ?: config('currency.base_code', 'USD'));
    }

    private function normalizeCurrencyCode(?string $code): string
    {
        return strtoupper(substr(trim((string) $code), 0, 10));
    }

    private function walletHasNoMoney(MerchantWallet $wallet): bool
    {
        return (float) $wallet->available_balance === 0.0
            && (float) $wallet->holding_balance === 0.0
            && (float) $wallet->pending_withdrawal_balance === 0.0
            && (float) $wallet->total_earned === 0.0
            && (float) $wallet->total_withdrawn === 0.0
            && (float) $wallet->total_fees === 0.0
            && (float) $wallet->total_refunded === 0.0;
    }

    private function holdingDays(): int
    {
        return max((int) config('saas.wallet.holding_days', 0), 0);
    }

    private function minimumWithdrawalAmount(): float
    {
        return $this->roundMoney((float) config('saas.wallet.min_withdrawal_amount', 0));
    }

    private function roundMoney(float $amount): float
    {
        return round($amount, 6);
    }
}
