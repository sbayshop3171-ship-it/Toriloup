<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformPlanUpsertRequest;
use App\Models\PlatformPlan;
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
            ->with(['limits', 'prices', 'features'])
            ->withCount('subscriptions')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('visibility'), function ($query) use ($request): void {
                $query->where('is_public', $request->string('visibility') === 'public');
            })
            ->orderBy('display_order')
            ->orderByDesc('is_recommended')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $plans->map(fn (PlatformPlan $plan) => $this->subscriptionManagerService->serializePlan($plan))->values(),
            'meta' => [
                'catalog_enforced' => $this->subscriptionManagerService->hasActivePublicPlans(),
            ],
        ]);
    }

    public function show(string $planCode): JsonResponse
    {
        $this->subscriptionManagerService->ensureDefaultPlans();

        $plan = PlatformPlan::query()->with(['limits', 'prices', 'features'])->where('code', $planCode)->firstOrFail();

        return response()->json([
            'status' => true,
            'data' => $this->subscriptionManagerService->serializePlan($plan),
        ]);
    }

    public function upsert(PlatformPlanUpsertRequest $request, string $planCode): JsonResponse
    {
        $oldPlan = PlatformPlan::query()->with(['limits', 'prices', 'features'])->where('code', $planCode)->first();

        $plan = $this->subscriptionManagerService->upsertPlan($planCode, $request->validated());

        $this->platformAuditLogService->log(
            'platform.plan.upserted',
            'platform_plan',
            $plan->id,
            $oldPlan ? $this->subscriptionManagerService->serializePlan($oldPlan) : [],
            $this->subscriptionManagerService->serializePlan($plan),
            $request,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'data' => $this->subscriptionManagerService->serializePlan($plan),
        ]);
    }
}
