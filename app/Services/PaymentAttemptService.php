<?php

namespace App\Services;

use App\Enums\PaymentAttemptStatus;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\TenantPaymentMethod;
use App\Services\Saas\TenantPaymentMethodCatalogService;
use Illuminate\Support\Str;

class PaymentAttemptService
{
    public function __construct(private readonly TenantPaymentMethodCatalogService $tenantPaymentMethodCatalogService)
    {
    }

    public function prepare(Order $order, string $gatewaySlug, ?string $idempotencyKey = null): PaymentAttempt
    {
        $gatewaySlug = $this->normalizeGatewaySlug($gatewaySlug);
        $idempotencyKey = $this->idempotencyKey($idempotencyKey);
        $existing = PaymentAttempt::withoutGlobalScopes()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof PaymentAttempt) {
            if ((int) $existing->order_id === (int) $order->id && $existing->gateway_slug === $gatewaySlug) {
                return $existing;
            }

            $idempotencyKey = $this->idempotencyKey(null);
        }

        $gateway = PaymentGateway::query()->where('slug', $gatewaySlug)->first();
        $tenantPaymentMethod = $this->tenantPaymentMethodFor($order, $gatewaySlug);

        return PaymentAttempt::withoutGlobalScopes()->create([
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'payment_gateway_id' => $gateway?->id,
            'tenant_payment_method_id' => $tenantPaymentMethod?->id,
            'gateway_slug' => $gatewaySlug,
            'status' => PaymentAttemptStatus::PENDING,
            'idempotency_key' => $idempotencyKey,
            'amount' => (float) $order->total,
            'currency_code' => $this->currencyForOrder($order),
            'started_at' => now(),
        ]);
    }

    public function start(Order $order, string $gatewaySlug, ?string $idempotencyKey = null): PaymentAttempt
    {
        $attempt = $this->prepare($order, $gatewaySlug, $idempotencyKey);

        if (!$this->isTerminal($attempt->status)) {
            $attempt->forceFill([
                'status' => PaymentAttemptStatus::PROCESSING,
                'started_at' => $attempt->started_at ?: now(),
            ])->save();
        }

        return $attempt;
    }

    public function markSucceeded(Order $order, string $gatewaySlug, ?string $providerTransactionId = null, array $payload = []): PaymentAttempt
    {
        $attempt = $this->locateAttempt($order, $gatewaySlug, $providerTransactionId);

        $attempt->forceFill([
            'status' => PaymentAttemptStatus::SUCCEEDED,
            'provider_transaction_id' => $providerTransactionId ?: $attempt->provider_transaction_id,
            'amount_verified' => (float) $order->total,
            'currency_verified' => $this->currencyForOrder($order),
            'backend_validation_passed' => true,
            'failure_reason' => null,
            'provider_payload_json' => $payload ?: $attempt->provider_payload_json,
            'verified_at' => $attempt->verified_at ?: now(),
            'finished_at' => $attempt->finished_at ?: now(),
        ])->save();

        return $attempt;
    }

    public function markFailed(Order $order, string $gatewaySlug, ?string $reason = null, array $payload = []): PaymentAttempt
    {
        return $this->markTerminal($order, $gatewaySlug, PaymentAttemptStatus::FAILED, $reason, $payload);
    }

    public function markCanceled(Order $order, string $gatewaySlug, ?string $reason = null, array $payload = []): PaymentAttempt
    {
        return $this->markTerminal($order, $gatewaySlug, PaymentAttemptStatus::CANCELED, $reason, $payload);
    }

    public function succeededProviderTransactionExists(Order $order, string $gatewaySlug, ?string $providerTransactionId): bool
    {
        if (blank($providerTransactionId)) {
            return false;
        }

        return PaymentAttempt::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('gateway_slug', $this->normalizeGatewaySlug($gatewaySlug))
            ->where('provider_transaction_id', $providerTransactionId)
            ->where('status', PaymentAttemptStatus::SUCCEEDED)
            ->exists();
    }

    private function markTerminal(Order $order, string $gatewaySlug, string $status, ?string $reason, array $payload): PaymentAttempt
    {
        $attempt = $this->locateAttempt($order, $gatewaySlug);

        if ($attempt->status === PaymentAttemptStatus::SUCCEEDED) {
            return $attempt;
        }

        $attempt->forceFill([
            'status' => $status,
            'backend_validation_passed' => false,
            'failure_reason' => $reason,
            'provider_payload_json' => $payload ?: $attempt->provider_payload_json,
            'finished_at' => $attempt->finished_at ?: now(),
        ])->save();

        return $attempt;
    }

    private function locateAttempt(Order $order, string $gatewaySlug, ?string $providerTransactionId = null): PaymentAttempt
    {
        $gatewaySlug = $this->normalizeGatewaySlug($gatewaySlug);

        if (filled($providerTransactionId)) {
            $attempt = PaymentAttempt::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->where('gateway_slug', $gatewaySlug)
                ->where('provider_transaction_id', $providerTransactionId)
                ->latest('id')
                ->first();

            if ($attempt instanceof PaymentAttempt) {
                return $attempt;
            }
        }

        $attempt = PaymentAttempt::withoutGlobalScopes()
            ->where('order_id', $order->id)
            ->where('gateway_slug', $gatewaySlug)
            ->whereIn('status', [PaymentAttemptStatus::PENDING, PaymentAttemptStatus::PROCESSING])
            ->latest('id')
            ->first();

        if ($attempt instanceof PaymentAttempt) {
            return $attempt;
        }

        return $this->prepare($order, $gatewaySlug);
    }

    private function tenantPaymentMethodFor(Order $order, string $gatewaySlug): ?TenantPaymentMethod
    {
        if (!filled($order->tenant_id)) {
            return null;
        }

        return TenantPaymentMethod::query()
            ->where('tenant_id', $order->tenant_id)
            ->get()
            ->first(function (TenantPaymentMethod $method) use ($gatewaySlug): bool {
                return $this->tenantPaymentMethodCatalogService->gatewaySlugForProviderCode($method->provider_code) === $gatewaySlug;
            });
    }

    private function normalizeGatewaySlug(string $gatewaySlug): string
    {
        return $this->tenantPaymentMethodCatalogService->gatewaySlugForProviderCode(
            Str::of($gatewaySlug)->lower()->trim()->toString()
        );
    }

    private function idempotencyKey(?string $idempotencyKey): string
    {
        $clean = preg_replace('/[^A-Za-z0-9:_.-]/', '', (string) $idempotencyKey);
        $clean = Str::limit((string) $clean, 110, '');

        return filled($clean) ? $clean : 'pa_'.Str::random(40);
    }

    private function currencyForOrder(Order $order): ?string
    {
        $order->loadMissing('tenant');

        return $order->tenant?->primary_currency_code ?: env('CURRENCY_CODE');
    }

    private function isTerminal(string $status): bool
    {
        return in_array($status, [
            PaymentAttemptStatus::SUCCEEDED,
            PaymentAttemptStatus::FAILED,
            PaymentAttemptStatus::CANCELED,
        ], true);
    }
}
