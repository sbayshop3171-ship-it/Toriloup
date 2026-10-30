<?php

namespace App\Http\Controllers\Admin;

use App\Models\Tenant;
use App\Models\TenantMember;
use App\Services\MerchantNotificationPreferenceService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly MerchantNotificationPreferenceService $preferenceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $this->currentMerchantTenant($request);

        return response()->json([
            'data' => $this->preferenceService->listFor($tenant, $request->user()),
        ]);
    }

    public function update(Request $request, string $eventKey): JsonResponse
    {
        $tenant = $this->currentMerchantTenant($request);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $this->preferenceService->update(
                $tenant,
                $request->user(),
                $eventKey,
                (bool) $validated['enabled']
            );
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'event_key' => ['The selected notification event is not supported.'],
            ]);
        }

        $preference = collect($this->preferenceService->listFor($tenant, $request->user()))
            ->firstWhere('event_key', $eventKey);

        return response()->json(['data' => $preference]);
    }

    private function currentMerchantTenant(Request $request): Tenant
    {
        $tenant = $this->tenantContext->current($request);
        abort_unless($tenant instanceof Tenant, 403, 'An active merchant workspace is required.');

        $isActiveMember = TenantMember::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($isActiveMember, 403, 'Active merchant membership is required.');

        return $tenant;
    }
}
