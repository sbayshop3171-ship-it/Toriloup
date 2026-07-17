<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\AdminSurfacePayloadService;
use App\Services\Saas\PlatformSupportSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantSupportSessionController extends Controller
{
    public function __construct(
        private readonly PlatformSupportSessionService $platformSupportSessionService,
        private readonly AdminSurfacePayloadService $adminSurfacePayloadService,
    ) {
    }

    public function consume(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'handoff_code' => ['required', 'string', 'max:100'],
        ]);

        $session = $this->platformSupportSessionService->consumeByHandoffCode((string) $validated['handoff_code']);
        $tenantMember = $session->tenantMember;
        $user = $session->impersonatedUser;

        abort_if($tenantMember === null || $user === null, 422, 'Support session could not be activated.');

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'merchant', [
            'token' => $session->getAttribute('merchant_plain_text_token'),
            'current_tenant' => $this->adminSurfacePayloadService->serializeTenantMembership($tenantMember),
            'support_session' => $this->platformSupportSessionService->serializeSession($session),
        ]), 201);
    }

    public function end(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->platformSupportSessionService->endByMerchant($sessionId, $request->user(), $request);

        return response()->json([
            'status' => true,
            'data' => $this->platformSupportSessionService->serializeSession($session),
        ]);
    }
}
