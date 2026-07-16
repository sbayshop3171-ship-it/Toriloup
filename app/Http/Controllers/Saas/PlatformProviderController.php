<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\PlatformProviderUpsertRequest;
use App\Models\PlatformProvider;
use App\Services\Saas\PlatformAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformProviderController extends Controller
{
    public function __construct(private readonly PlatformAuditLogService $platformAuditLogService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $providers = PlatformProvider::query()
            ->when($request->filled('provider_type'), fn ($query) => $query->where('provider_type', $request->string('provider_type')))
            ->orderBy('provider_type')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $providers->map(fn (PlatformProvider $provider) => $this->serializeProvider($provider))->values(),
        ]);
    }

    public function upsert(PlatformProviderUpsertRequest $request, string $providerCode): JsonResponse
    {
        $provider = PlatformProvider::query()->where('provider_code', $providerCode)->first();
        $oldValues = $provider?->only(['provider_type', 'provider_code', 'name', 'status', 'config_json']) ?? [];

        $provider = PlatformProvider::query()->updateOrCreate(
            ['provider_code' => $providerCode],
            [
                'provider_type' => $request->string('provider_type'),
                'name' => $request->string('name'),
                'status' => $request->boolean('status', true),
                'config_json' => $request->input('config_json', []),
            ]
        );

        $this->platformAuditLogService->log(
            'platform.provider.upserted',
            'platform_provider',
            $provider->id,
            $oldValues,
            $provider->only(['provider_type', 'provider_code', 'name', 'status', 'config_json']),
            $request,
            $request->user()
        );

        return response()->json([
            'status' => true,
            'data' => $this->serializeProvider($provider),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProvider(PlatformProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'provider_type' => $provider->provider_type,
            'provider_code' => $provider->provider_code,
            'name' => $provider->name,
            'status' => $provider->status,
            'config_json' => $provider->config_json,
        ];
    }
}
