<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\TenantSubscriptionAssignRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionManagerService $subscriptionManagerService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->subscriptionManagerService->ensureDefaultPlans();

        $subscriptions = TenantSubscription::query()
            ->with(['plan.limits', 'plan.prices', 'plan.features', 'tenant', 'invoices' => fn ($query) => $query->latest('id')])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', (int) $request->integer('tenant_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $subscriptions->map(fn (TenantSubscription $subscription) => $this->subscriptionManagerService->serializeSubscription($subscription))->values(),
        ]);
    }

    public function show(int $subscriptionId): JsonResponse
    {
        $subscription = TenantSubscription::query()
            ->with(['plan.limits', 'plan.prices', 'plan.features', 'tenant', 'invoices' => fn ($query) => $query->latest('id')])
            ->findOrFail($subscriptionId);

        return response()->json([
            'status' => true,
            'data' => $this->subscriptionManagerService->serializeSubscription($subscription),
        ]);
    }

    public function assignToTenant(TenantSubscriptionAssignRequest $request, int $tenantId): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $subscription = $this->subscriptionManagerService->assignPlanToTenant(
            $tenant,
            (string) $request->input('plan_code'),
            (string) $request->input('billing_interval', 'monthly'),
            $request->user(),
            $request->input('metadata_json', [])
        );

        $this->platformAuditLogService->log(
            'platform.subscription.assigned',
            'tenant_subscription',
            $subscription->id,
            [],
            $this->subscriptionManagerService->serializeSubscription($subscription),
            $request,
            $request->user(),
            $tenant
        );

        return response()->json([
            'status' => true,
            'data' => $this->subscriptionManagerService->serializeSubscription($subscription),
        ]);
    }

    public function markInvoicePaid(Request $request, int $subscriptionId, int $invoiceId): JsonResponse
    {
        $subscription = TenantSubscription::query()->with(['plan.limits', 'plan.prices', 'plan.features', 'tenant', 'invoices'])->findOrFail($subscriptionId);
        $invoice = TenantSubscriptionInvoice::query()
            ->where('tenant_subscription_id', $subscription->id)
            ->findOrFail($invoiceId);

        $invoice = $this->subscriptionManagerService->markInvoicePaid($invoice);

        $this->platformAuditLogService->log(
            'platform.invoice.paid',
            'tenant_subscription_invoice',
            $invoice->id,
            [],
            [
                'status' => $invoice->status,
                'paid_at' => $invoice->paid_at,
            ],
            $request,
            $request->user(),
            $subscription->tenant
        );

        return response()->json([
            'status' => true,
            'data' => [
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at,
                ],
                'subscription' => $this->subscriptionManagerService->serializeSubscription($subscription->fresh(['plan.limits', 'plan.prices', 'plan.features', 'tenant', 'invoices' => fn ($query) => $query->latest('id')])),
            ],
        ]);
    }
}
