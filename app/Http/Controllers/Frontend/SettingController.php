<?php

namespace App\Http\Controllers\Frontend;


use App\Http\Resources\SettingResource;
use App\Models\Tenant;
use App\Services\SettingService;
use App\Services\Saas\TenantSettingsService;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private SettingService $settingService;
    private TenantSettingsService $tenantSettingsService;

    public function __construct(SettingService $settingService, TenantSettingsService $tenantSettingsService)
    {
        $this->settingService = $settingService;
        $this->tenantSettingsService = $tenantSettingsService;
    }

    public function index(Request $request) : \Illuminate\Http\Response | SettingResource | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            $tenant = $request->attributes->get(config('tenancy.tenant_request_attribute', 'saas.tenant'));

            if ($tenant instanceof Tenant) {
                return new SettingResource($this->tenantSettingsService->mergedForTenant($tenant));
            }

            return new SettingResource($this->settingService->list());
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
