<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Saas\PlatformSupportSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformSupportSessionController extends Controller
{
    public function __construct(
        private readonly PlatformSupportSessionService $platformSupportSessionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $sessions = $this->platformSupportSessionService->list($request->only(['status', 'tenant_id', 'q', 'limit']));

        return response()->json([
            'status' => true,
            'data' => $sessions->map(fn ($session) => $this->platformSupportSessionService->serializeSession($session, true))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $tenant = Tenant::query()->findOrFail((int) $validated['tenant_id']);
        $session = $this->platformSupportSessionService->start(
            $tenant,
            $request->user(),
            $validated['reason'] ?? null,
            $request
        );

        return response()->json([
            'status' => true,
            'data' => $this->platformSupportSessionService->serializeSession($session, true),
        ], 201);
    }

    public function end(Request $request, int $sessionId): JsonResponse
    {
        $session = $this->platformSupportSessionService->endByOwner($sessionId, $request->user(), $request);

        return response()->json([
            'status' => true,
            'data' => $this->platformSupportSessionService->serializeSession($session, true),
        ]);
    }
}
