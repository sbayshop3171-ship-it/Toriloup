<?php

namespace App\Http\Controllers\Admin;

use App\Services\PushDeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController
{
    public function __construct(private readonly PushDeviceTokenService $deviceTokenService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'installation_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,web'],
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $registration = $this->deviceTokenService->register(
            $request->user(),
            $validated['installation_id'],
            $validated['platform'],
            $validated['token']
        );

        return response()->json([
            'data' => [
                'installation_id' => $registration->installation_id,
                'platform' => $registration->platform,
                'registered' => true,
            ],
        ]);
    }

    public function destroy(Request $request, string $installationId): JsonResponse
    {
        abort_unless((bool) preg_match('/^[0-9a-f-]{36}$/i', $installationId), 404);

        $removed = $this->deviceTokenService->unregister($request->user(), $installationId);

        return response()->json(['data' => ['removed' => $removed]]);
    }
}
