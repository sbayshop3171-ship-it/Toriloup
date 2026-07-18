<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\MerchantPayoutMethod;
use App\Models\MerchantWallet;
use App\Models\MerchantWalletTransaction;
use App\Models\MerchantWithdrawal;
use App\Models\Tenant;
use App\Services\Saas\MerchantWalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PlatformWalletController extends Controller
{
    public function __construct(private readonly MerchantWalletService $walletService)
    {
    }

    public function overview(): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return response()->json([
                'status' => true,
                'setup_required' => true,
                'message' => 'Wallet database tables are not ready yet. Please run migrations, then refresh this page.',
                'summary' => $this->emptySummary(),
                'recent_withdrawals' => [],
            ]);
        }

        return response()->json([
            'status' => true,
            'summary' => $this->walletService->platformOverview(),
            'recent_withdrawals' => MerchantWithdrawal::withoutGlobalScopes()
                ->with(['tenant:id,name,slug', 'payoutMethod', 'requestedBy:id,name,email,phone'])
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (MerchantWithdrawal $withdrawal): array => $this->walletService->serializeWithdrawal($withdrawal))
                ->values(),
        ]);
    }

    public function wallets(Request $request): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return response()->json($this->emptyPaginatedResponse());
        }

        $wallets = MerchantWallet::withoutGlobalScopes()
            ->with('tenant:id,name,slug,status,primary_currency_code')
            ->when($request->filled('tenant_id'), fn (Builder $query): Builder => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('q'), function (Builder $query) use ($request): Builder {
                $search = '%' . $request->string('q') . '%';

                return $query->whereHas('tenant', fn (Builder $tenantQuery): Builder => $tenantQuery
                    ->where('name', 'like', $search)
                    ->orWhere('slug', 'like', $search));
            })
            ->latest('id')
            ->paginate($this->perPage($request));

        return response()->json([
            'status' => true,
            'data' => collect($wallets->items())->map(function (MerchantWallet $wallet): array {
                return [
                    ...$this->walletService->serializeWallet($wallet),
                    'tenant' => $wallet->tenant?->only(['id', 'name', 'slug', 'status', 'primary_currency_code']),
                ];
            })->values(),
            'meta' => [
                'current_page' => $wallets->currentPage(),
                'last_page' => $wallets->lastPage(),
                'per_page' => $wallets->perPage(),
                'total' => $wallets->total(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return response()->json($this->emptyPaginatedResponse());
        }

        $transactions = $this->transactionQuery($request)->paginate($this->perPage($request));

        return response()->json([
            'status' => true,
            'data' => collect($transactions->items())
                ->map(fn (MerchantWalletTransaction $transaction): array => [
                    ...$this->walletService->serializeTransaction($transaction),
                    'tenant' => $transaction->tenant?->only(['id', 'name', 'slug']),
                ])
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
        if (!$this->walletStorageReady()) {
            return response()->json($this->emptyPaginatedResponse());
        }

        $withdrawals = $this->withdrawalQuery($request)->paginate($this->perPage($request));

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

    public function approveWithdrawal(Request $request, int $withdrawalId): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return $this->storageNotReadyResponse();
        }

        $payload = $request->validate([
            'payout_reference' => ['nullable', 'string', 'max:120'],
            'admin_note' => ['nullable', 'string', 'max:700'],
        ]);

        try {
            $withdrawal = $this->walletService->approveWithdrawal(
                MerchantWithdrawal::withoutGlobalScopes()->findOrFail($withdrawalId),
                $request->user(),
                $payload['payout_reference'] ?? null,
                $payload['admin_note'] ?? null
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $this->statusCode($exception));
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->serializeWithdrawal($withdrawal),
        ]);
    }

    public function rejectWithdrawal(Request $request, int $withdrawalId): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return $this->storageNotReadyResponse();
        }

        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:700'],
        ]);

        try {
            $withdrawal = $this->walletService->rejectWithdrawal(
                MerchantWithdrawal::withoutGlobalScopes()->findOrFail($withdrawalId),
                $request->user(),
                $payload['reason']
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $this->statusCode($exception));
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->serializeWithdrawal($withdrawal),
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
            'data' => $this->walletService->payoutMethods(),
        ]);
    }

    public function upsertPayoutMethod(Request $request): JsonResponse
    {
        if (!Schema::hasTable('merchant_payout_methods')) {
            return $this->storageNotReadyResponse();
        }

        $payload = $request->validate([
            'code' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:700'],
            'instructions' => ['nullable', 'string', 'max:1200'],
            'fields' => ['nullable', 'array'],
            'fields_json' => ['nullable', 'array'],
            'fields.*.key' => ['nullable', 'string', 'max:80'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:120'],
            'fields.*.type' => ['nullable', 'in:text,email,number,url,textarea,select'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.instructions' => ['nullable', 'string', 'max:300'],
            'fields.*.width' => ['nullable', 'integer', 'in:25,33,50,100'],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'boolean'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_type' => ['nullable', 'in:none,fixed,percent'],
            'fee_value' => ['nullable', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $method = $this->walletService->upsertPayoutMethod($payload);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'status' => false,
            ], $this->statusCode($exception));
        }

        return response()->json([
            'status' => true,
            'data' => $this->walletService->serializePayoutMethod($method),
        ]);
    }

    public function statement(Request $request): JsonResponse
    {
        if (!$this->walletStorageReady()) {
            return response()->json([
                'status' => true,
                'setup_required' => true,
                'data' => [
                    'tenant' => null,
                    'summary' => $this->emptySummary(),
                    'totals' => $this->emptyTotals(),
                    'filters' => [
                        'tenant_id' => $request->input('tenant_id'),
                        'from_date' => $request->input('from_date'),
                        'to_date' => $request->input('to_date'),
                    ],
                ],
            ]);
        }

        $request->validate([
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
        ]);

        $tenant = $request->filled('tenant_id') ? Tenant::query()->findOrFail($request->integer('tenant_id')) : null;
        $transactions = $this->transactionQuery($request)->get();

        return response()->json([
            'status' => true,
            'data' => [
                'tenant' => $tenant?->only(['id', 'name', 'slug']),
                'summary' => $this->walletService->platformOverview(),
                'totals' => [
                    'gross_sales' => (float) $transactions->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)->sum('gross_amount'),
                    'net_sales' => (float) $transactions->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)->sum('amount'),
                    'fees' => (float) $transactions->sum('fee_amount'),
                    'refunds' => (float) $transactions->where('type', MerchantWalletService::TYPE_REFUND_ADJUSTMENT)->sum('amount'),
                    'withdrawal_requests' => (float) $transactions->where('type', MerchantWalletService::TYPE_WITHDRAWAL_REQUEST)->sum('gross_amount'),
                    'withdrawal_reversals' => (float) $transactions->where('type', MerchantWalletService::TYPE_WITHDRAWAL_REVERSAL)->sum('amount'),
                ],
                'filters' => [
                    'tenant_id' => $request->input('tenant_id'),
                    'from_date' => $request->input('from_date'),
                    'to_date' => $request->input('to_date'),
                ],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        if (!$this->walletStorageReady()) {
            return response()->streamDownload(function (): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Wallet storage is not ready. Run migrations, then export again.']);
                fclose($output);
            }, 'platform-wallet-export.csv', ['Content-Type' => 'text/csv']);
        }

        $request->validate([
            'type' => ['nullable', 'in:transactions,withdrawals,wallets'],
        ]);

        $type = $request->input('type', 'transactions');

        if ($type === 'withdrawals') {
            return $this->exportWithdrawals($request);
        }

        if ($type === 'wallets') {
            return $this->exportWallets($request);
        }

        $rows = $this->transactionQuery($request)->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Tenant', 'Type', 'Direction', 'Status', 'Gross', 'Fee', 'Net', 'Balance After', 'Order', 'Description']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->created_at?->toDateTimeString(),
                    $row->tenant?->name,
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
        }, 'platform-wallet-transactions.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportWithdrawals(Request $request): StreamedResponse
    {
        $rows = $this->withdrawalQuery($request)->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Requested At', 'Request No', 'Tenant', 'Method', 'Destination', 'Status', 'Amount', 'Fee', 'Net', 'Reference', 'Admin Note']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->requested_at?->toDateTimeString(),
                    $row->request_no,
                    $row->tenant?->name,
                    $row->payoutMethod?->name,
                    collect($this->walletService->serializeWithdrawal($row)['destination_details'] ?? [])
                        ->map(fn (array $detail): string => ($detail['label'] ?? $detail['key']) . ': ' . ($detail['value'] ?? ''))
                        ->implode(' | '),
                    $row->status,
                    (float) $row->amount,
                    (float) $row->fee_amount,
                    (float) $row->net_amount,
                    $row->payout_reference,
                    $row->admin_note,
                ]);
            }

            fclose($output);
        }, 'platform-wallet-withdrawals.csv', ['Content-Type' => 'text/csv']);
    }

    private function exportWallets(Request $request): StreamedResponse
    {
        $rows = MerchantWallet::withoutGlobalScopes()
            ->with('tenant:id,name,slug')
            ->when($request->filled('tenant_id'), fn (Builder $query): Builder => $query->where('tenant_id', $request->integer('tenant_id')))
            ->latest('id')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Tenant', 'Currency', 'Available', 'Holding', 'Pending Withdrawal', 'Earned', 'Withdrawn', 'Fees', 'Refunded']);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->tenant?->name,
                    $row->currency_code,
                    (float) $row->available_balance,
                    (float) $row->holding_balance,
                    (float) $row->pending_withdrawal_balance,
                    (float) $row->total_earned,
                    (float) $row->total_withdrawn,
                    (float) $row->total_fees,
                    (float) $row->total_refunded,
                ]);
            }

            fclose($output);
        }, 'platform-wallets.csv', ['Content-Type' => 'text/csv']);
    }

    private function transactionQuery(Request $request): Builder
    {
        return MerchantWalletTransaction::withoutGlobalScopes()
            ->with(['tenant:id,name,slug', 'order:id,order_serial_no,total,status,payment_status', 'withdrawal:id,request_no,status'])
            ->when($request->filled('tenant_id'), fn (Builder $query): Builder => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('type'), fn (Builder $query): Builder => $query->where('type', $request->string('type')))
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->string('status')))
            ->when($request->filled('from_date'), fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $request->input('to_date')))
            ->latest('id');
    }

    private function withdrawalQuery(Request $request): Builder
    {
        return MerchantWithdrawal::withoutGlobalScopes()
            ->with(['tenant:id,name,slug', 'payoutMethod', 'requestedBy:id,name,email,phone'])
            ->when($request->filled('tenant_id'), fn (Builder $query): Builder => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn (Builder $query): Builder => $query->where('status', $request->string('status')))
            ->when($request->filled('from_date'), fn (Builder $query): Builder => $query->whereDate('requested_at', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn (Builder $query): Builder => $query->whereDate('requested_at', '<=', $request->input('to_date')))
            ->latest('id');
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

    /**
     * @return array<string, float|int>
     */
    private function emptySummary(): array
    {
        return [
            'wallets_count' => 0,
            'available_balance' => 0,
            'holding_balance' => 0,
            'pending_withdrawal_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'total_fees' => 0,
            'total_refunded' => 0,
            'withdrawals_pending_count' => 0,
            'withdrawals_pending_amount' => 0,
            'withdrawals_approved_count' => 0,
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function emptyTotals(): array
    {
        return [
            'gross_sales' => 0,
            'net_sales' => 0,
            'fees' => 0,
            'refunds' => 0,
            'withdrawal_requests' => 0,
            'withdrawal_reversals' => 0,
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
