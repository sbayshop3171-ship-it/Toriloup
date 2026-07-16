<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\SubscriptionManagerService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'status' => true,
            'tenant' => $tenant?->only(['id', 'name', 'slug', 'status', 'plan_code']),
            'subscription' => $subscription ? $this->subscriptionManagerService->serializeSubscription($subscription) : null,
            'usage' => $tenant ? $this->subscriptionManagerService->usageSummary($tenant) : [],
        ]);
    }

    public function invoices(): JsonResponse
    {
        $tenant = $this->tenantContext->current();
        $subscription = $tenant ? $this->subscriptionManagerService->currentSubscription($tenant) : null;

        return response()->json([
            'status' => true,
            'data' => $subscription ? $this->subscriptionManagerService->serializeSubscription($subscription)['invoices'] : [],
        ]);
    }
}
