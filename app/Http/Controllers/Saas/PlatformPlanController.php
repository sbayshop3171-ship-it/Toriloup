<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformPlanUpsertRequest;
use App\Models\PlatformPlan;
use App\Models\PlatformPlanLimit;
use App\Services\Saas\PlatformAuditLogService;
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionManagerService $subscriptionManagerService,
        private readonly PlatformAuditLogService $platformAuditLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->subscriptionManagerService->ensureDefaultPlans();

        $plans = PlatformPlan::query()
            ->with('limits')
            ->withCount('subscriptions')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('monthly_price')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $plans->map(fn (PlatformPlan $plan) => $this->serializePlan($plan))->values(),
        ]);
    }

    public function show(string $planCode): JsonResponse
    {
        $this->subscriptionManagerService->ensureDefaultPlans();

        $plan = PlatformPlan::query()->with('limits')->where('code', $planCode)->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $this->serializePlan($plan),
        ]);
    }

    public function upsert(PlatformPlanUpsertRequest $request, string $planCode): JsonResponse
    {
        $oldPlan = PlatformPlan::query()->with('limits')->where('code', $planCode)->first();

        $plan = $this->subscriptionManagerService->upsertPlan($planCode, $request->validated());

        $this->platformAuditLogService->log(
            'platform.plan.upserted',
            'platform_plan',
            $plan->id,
            $oldPlan ? $this->serializePlan($oldPlan) : [],
            $this->serializePlan($plan),
            $request,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializePlan($plan),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePlan(PlatformPlan $plan): array
    {
        $plan->loadMissing('limits');

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'status' => $plan->status,
            'currency_code' => $plan->currency_code,
            'monthly_price' => $plan->monthly_price,
            'yearly_price' => $plan->yearly_price,
            'trial_days' => $plan->trial_days,
            'transaction_fee_type' => $plan->transaction_fee_type,
            'transaction_fee_value' => $plan->transaction_fee_value,
            'metadata_json' => $plan->metadata_json,
            'subscribers_count' => $plan->subscriptions_count ?? $plan->subscriptions()->count(),
            'limits' => $plan->limits->map(fn (PlatformPlanLimit $limit) => [
                'key' => $limit->limit_key,
                'value' => $limit->limit_value,
                'is_unlimited' => $limit->is_unlimited,
            ])->values(),
        ];
    }
}
