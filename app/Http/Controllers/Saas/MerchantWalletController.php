<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\MerchantWalletTransaction;
use App\Models\MerchantWithdrawal;
use App\Models\Tenant;
use App\Services\Saas\MerchantWalletService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MerchantWalletController extends Controller
{
    public function __construct(
        private readonly MerchantWalletService $walletService,
        private readonly TenantContext $tenantContext
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if (!$this->walletStorageReady()) {
            return response()->json([
                'status' => true,
                'setup_required' => true,
                'message' => 'Wallet database tables are not ready yet. Please run migrations, then refresh this page.',
                'data' => $this->emptySummary($tenant),
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->summary($tenant),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if (!$this->walletStorageReady()) {
            return response()->json($this->emptyPaginatedResponse());
        }

        $this->walletService->releaseEligibleHoldings($tenant);

        $transactions = $this->transactionQuery($tenant, $request)
            ->paginate($this->perPage($request));

        return response()->json([
            'status' => true,
            'data' => collect($transactions->items())
                ->map(fn (MerchantWalletTransaction $transaction): array => $this->walletService->serializeTransaction($transaction))
                ->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        if (!$this->walletStorageReady()) {
            return response()->json($this->emptyPaginatedResponse());
        }

        $withdrawals = MerchantWithdrawal::withoutGlobalScopes()
            ->with('payoutMethod')
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate($this->perPage($request));

        return response()->json([
            'status' => true,
            'data' => collect($withdrawals->items())
                ->map(fn (MerchantWithdrawal $withdrawal): array => $this->walletService->serializeWithdrawal($withdrawal))
                ->values(),
            'meta' => [
                'current_page' => $withdrawals->currentPage(),
                'last_page' => $withdrawals->lastPage(),
                'per_page' => $withdrawals->perPage(),
                'total' => $withdrawals->total(),
            ],
        ]);
    }

    public function payoutMethods(): JsonResponse
    {
        if (!Schema::hasTable('merchant_payout_methods')) {
            return response()->json([
                'status' => true,
                'data' => [],
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->payoutMethods(activeOnly: true),
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return $this->storageNotReadyResponse();
        }

        $payload = $request->validate([
            'payout_method_id' => ['required', 'integer', 'exists:merchant_payout_methods,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'array'],
            'destination.*' => ['nullable'],
            'merchant_note' => ['nullable', 'string', 'max:700'],
        ]);

        try {
            $withdrawal = $this->walletService->requestWithdrawal($this->tenant($request), $request->user(), $payload);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $this->statusCode($exception));
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->serializeWithdrawal($withdrawal),
        ], 201);
    }

    public function statement(Request $request): JsonResponse
    {
        $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $tenant = $this->tenant($request);

        if (!$this->walletStorageReady()) {
            return response()->json([
                'status' => true,
                'setup_required' => true,
                'data' => [
                    'tenant' => $tenant->only(['id', 'name', 'slug']),
                    'wallet' => $this->emptyWallet($tenant),
                    'totals' => $this->emptyTotals(),
                    'filters' => [
                        'from_date' => $request->input('from_date'),
                        'to_date' => $request->input('to_date'),
                    ],
                ],
            ]);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'tenant' => $tenant->only(['id', 'name', 'slug']),
                'wallet' => $this->walletService->serializeWallet($this->walletService->walletForTenant($tenant)),
                'totals' => $this->walletService->statement(
                    $tenant,
                    $request->input('from_date'),
                    $request->input('to_date')
                ),
                'filters' => [
                    'from_date' => $request->input('from_date'),
                    'to_date' => $request->input('to_date'),
                ],
            ],
        ]);
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        $tenant = $this->tenant($request);

        if (!$this->walletStorageReady()) {
            return response()->streamDownload(function (): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Wallet storage is not ready. Run migrations, then export again.']);
                fclose($output);
            }, 'merchant-wallet-transactions.csv', ['Content-Type' => 'text/csv']);
        }

        $rows = $this->transactionQuery($tenant, $request)->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Type', 'Direction', 'Status', 'Gross', 'Fee', 'Net', 'Balance After', 'Order', 'Description']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->created_at?->toDateTimeString(),
                    $row->type,
                    $row->direction,
                    $row->status,
                    (float) $row->gross_amount,
                    (float) $row->fee_amount,
                    (float) $row->amount,
                    (float) $row->balance_after,
                    $row->order?->order_serial_no,
                    $row->description,
                ]);
            }

            fclose($output);
        }, 'merchant-wallet-transactions.csv', ['Content-Type' => 'text/csv']);
    }

    private function transactionQuery(Tenant $tenant, Request $request): Builder
    {
        return MerchantWalletTransaction::withoutGlobalScopes()
            ->with(['order:id,order_serial_no,total,status,payment_status', 'withdrawal:id,request_no,status'])
            ->where('tenant_id', $tenant->id)
            ->when($request->filled('type'), fn (Builder $query): Builder => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->string('status')))
            ->when($request->filled('from_date'), fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest('id');
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));
        $tenant = $tenant instanceof Tenant ? $tenant : $this->tenantContext->current($request);

        abort_unless($tenant instanceof Tenant, 404, 'Tenant context was not resolved.');

        return $tenant;
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }

    private function walletStorageReady(): bool
    {
        foreach (['merchant_wallets', 'merchant_wallet_transactions', 'merchant_withdrawals', 'merchant_payout_methods'] as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummary(Tenant $tenant): array
    {
        return [
            'wallet' => $this->emptyWallet($tenant),
            'period_totals' => $this->emptyTotals(),
            'recent_transactions' => [],
            'recent_withdrawals' => [],
            'settings' => [
                'holding_days' => max((int) config('saas.wallet.holding_days', 0), 0),
                'min_withdrawal_amount' => max((float) config('saas.wallet.min_withdrawal_amount', 0), 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWallet(Tenant $tenant): array
    {
        return [
            'id' => null,
            'tenant_id' => $tenant->id,
            'currency_code' => $tenant->primary_currency_code ?: 'USD',
            'available_balance' => 0,
            'holding_balance' => 0,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'total_fees' => 0,
            'total_refunded' => 0,
            'last_settled_at' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'credits_count' => 0,
            'debits_count' => 0,
            'gross_sales' => 0,
            'net_sales' => 0,
            'fees' => 0,
            'refunds' => 0,
            'withdrawal_requests' => 0,
            'withdrawal_reversals' => 0,
            'credits_total' => 0,
            'debits_total' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPaginatedResponse(): array
    {
        return [
            'status' => true,
            'setup_required' => true,
            'message' => 'Wallet database tables are not ready yet. Please run migrations, then refresh this page.',
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0,
            ],
        ];
    }

    private function storageNotReadyResponse(): JsonResponse
    {
        return response()->json([
            'status' => false,
            'setup_required' => true,
            'message' => 'Wallet database tables are not ready yet. Please run migrations, then refresh this page.',
        ], 503);
    }

    private function statusCode(Throwable $exception): int
    {
        return $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 422;
    }
}
