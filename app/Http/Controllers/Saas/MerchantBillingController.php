<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\MerchantBillingCheckoutRequest;
use App\Models\TenantSubscriptionInvoice;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantBillingController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    public function summary(): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $subscription = $tenant ? $this->subscriptionManagerService->currentSubscription($tenant) : null;
        $pendingSession = $tenant ? $this->subscriptionManagerService->pendingCheckoutSession($tenant) : null;
        $features = $tenant ? $this->subscriptionManagerService->featureSummary($tenant) : [];

        return response()->json([
            'status' => true,
            'tenant' => $tenant?->only(['id', 'name', 'slug', 'status', 'plan_code', 'billing_exempt_until_plan_change', 'billing_grandfathered_at']),
            'subscription' => $subscription ? $this->subscriptionManagerService->serializeSubscription($subscription) : null,
            'usage' => $tenant ? $this->subscriptionManagerService->usageSummary($tenant) : [],
            'features' => $features,
            'pending_upgrade' => $pendingSession ? $this->subscriptionManagerService->serializeCheckoutSession($pendingSession) : null,
            'catalog' => [
                'has_public_paid_plans' => $this->subscriptionManagerService->hasPublicPaidPlans(),
                'has_active_public_plans' => $this->subscriptionManagerService->hasActivePublicPlans(),
                'enforced' => $tenant ? $this->subscriptionManagerService->billingEnforcedForTenant($tenant) : false,
                'billing_exempt' => $tenant ? $this->subscriptionManagerService->tenantBillingExempt($tenant) : false,
                'mode' => $features['mode'] ?? null,
            ],
        ]);
    }

    public function invoices(): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $invoices = $tenant
            ? TenantSubscriptionInvoice::query()
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->get()
                ->map(fn (TenantSubscriptionInvoice $invoice) => $this->subscriptionManagerService->serializeInvoice($invoice))
                ->values()
            : collect();

        return response()->json([
            'status' => true,
            'data' => $invoices,
        ]);
    }

    public function plans(): JsonResponse
    {
        $tenant = $this->tenantContext->current();

        return response()->json([
            'status' => true,
            'data' => $tenant ? $this->subscriptionManagerService->merchantVisiblePlans($tenant) : [],
        ]);
    }

    public function checkout(MerchantBillingCheckoutRequest $request): JsonResponse
    {
        $tenant = $this->tenantContext->current();

        abort_if($tenant === null, 404, 'Tenant not resolved.');

        $result = $this->subscriptionManagerService->beginMerchantCheckout(
            $tenant,
            (string) $request->input('plan_code'),
            (string) $request->input('billing_interval'),
            $request->user(),
            ['origin' => 'merchant_billing_page']
        );

        return response()->json([
            'status' => true,
            'data' => $result,
        ]);
    }
}
