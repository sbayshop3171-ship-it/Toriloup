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
        return response()->json([
            'status' => true,
            'data' => $this->walletService->summary($this->tenant($request)),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
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
        return response()->json([
            'status' => true,
            'data' => $this->walletService->payoutMethods(activeOnly: true),
        ]);
    }

    public function requestWithdrawal(Request $request): JsonResponse
    {
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

    private function statusCode(Throwable $exception): int
    {
        return $exception->getCode() >= 400 && $exception->getCode() < 600 ? $exception->getCode() : 422;
    }
}
