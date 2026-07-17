<?php

namespace App\Http\Controllers\Saas;

use App\Enums\Role;
use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\MerchantRegisterRequest;
use App\Models\User;
use App\Services\Saas\AdminSurfacePayloadService;
use App\Services\Saas\PlatformSupportSessionService;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminSurfaceAuthController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $tenantProvisioningService,
        private readonly AdminSurfacePayloadService $adminSurfacePayloadService,
        private readonly PlatformSupportSessionService $platformSupportSessionService,
    ) {
    }

    public function platformLogin(Request $request): JsonResponse
    {
        $user = $this->authenticateAdminUser($request);

        if ((int) $user->myRole !== Role::ADMIN) {
            return response()->json([
                'errors' => ['validation' => 'Only owner-level admin accounts can access the platform login.'],
            ], 403);
        }

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'platform'), 201);
    }

    public function merchantLogin(Request $request): JsonResponse
    {
        $user = $this->authenticateAdminUser($request);

        if ((int) $user->myRole === Role::ADMIN) {
            return response()->json([
                'errors' => ['validation' => 'Owner accounts must sign in through owner.company.com only.'],
            ], 403);
        }

        $tenantMembers = $this->adminSurfacePayloadService->activeTenantMembers($user);

        if ($tenantMembers->isEmpty()) {
            return response()->json([
                'errors' => ['validation' => 'Merchant account is not attached to any active store.'],
            ], 403);
        }

        return response()->json($this->adminSurfacePayloadService->payloadFor($user, 'merchant'), 201);
    }

    public function merchantRegister(MerchantRegisterRequest $request): JsonResponse
    {
        $result = $this->tenantProvisioningService->registerMerchant($request->validated());

        return response()->json($this->adminSurfacePayloadService->payloadFor($result['user'], 'merchant', [
            'tenant' => $this->adminSurfacePayloadService->serializeTenant($result['tenant']),
            'domain' => $result['domain']->only(['hostname', 'domain_type', 'is_primary', 'is_fallback', 'verification_status']),
            'auto_live_checks' => $result['checks'],
        ]), 201);
    }

    public function me(Request $request): JsonResponse
    {
        $surface = (string) $request->route('surface', 'merchant');
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json($this->adminSurfacePayloadService->mePayload($user, $surface));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token !== null) {
            $supportSession = $this->platformSupportSessionService->currentForToken($user, $token->id);

            if ($supportSession !== null) {
                $this->platformSupportSessionService->endByMerchant($supportSession->id, $user, $request);
            }

            $token->delete();
        }

        return response()->json([
            'message' => trans('all.message.logout_success'),
        ]);
    }

    private function authenticateAdminUser(Request $request): User
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => $request['phone'] ? ['nullable', 'string', 'email', 'max:255'] : ['required', 'string', 'email', 'max:255'],
                'phone' => $request['email'] ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'max:20'],
                'country_code' => $request['email'] ? ['nullable', 'string', 'max:20'] : ['required', 'string', 'max:20'],
                'password' => ['required', 'string', 'min:6'],
            ],
        );

        if ($validator->fails()) {
            return abort(response()->json([
                'errors' => $validator->errors(),
            ], 422));
        }

        $user = blank($request->email)
            ? User::query()->where('phone', $request->phone)->where('country_code', $request->country_code)->first()
            : User::query()->where('email', $request->email)->first();

        if ($user === null || (int) $user->status !== Status::ACTIVE || !Hash::check((string) $request->password, (string) $user->password)) {
            return abort(response()->json([
                'errors' => ['validation' => trans('all.message.credentials_invalid')],
            ], 400));
        }

        if (!isset($user->roles[0]) || (int) $user->myRole === Role::CUSTOMER) {
            return abort(response()->json([
                'errors' => ['validation' => 'Customer account cannot access admin panel login.'],
            ], 403));
        }

        return $user;
    }
}
