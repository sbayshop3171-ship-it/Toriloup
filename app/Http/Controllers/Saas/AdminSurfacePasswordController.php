<?php

namespace App\Http\Controllers\Saas;

use App\Enums\Role;
use App\Enums\Status;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Requests\SignupEmailRequest;
use App\Http\Requests\SignupPhoneRequest;
use App\Http\Requests\VerifyEmailRequest;
use App\Http\Requests\VerifyPhoneRequest;
use App\Models\User;
use App\Services\MenuService;
use App\Services\OtpManagerService;
use App\Services\PermissionService;
use App\Services\Saas\AdminSurfacePayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSurfacePasswordController extends ForgotPasswordController
{
    public function __construct(
        OtpManagerService $otpManagerService,
        PermissionService $permissionService,
        MenuService $menuService,
        private readonly AdminSurfacePayloadService $adminSurfacePayloadService,
    ) {
        parent::__construct($otpManagerService, $permissionService, $menuService);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return parent::forgotPassword($request);
    }

    public function otpPhone(SignupPhoneRequest $request): \Illuminate\Http\Response | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return parent::otpPhone($request);
    }

    public function otpEmail(SignupEmailRequest $request): \Illuminate\Http\Response | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return parent::otpEmail($request);
    }

    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return parent::verifyPhone($request);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        return parent::verifyEmail($request);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validationError = $this->validateResetRequest($request);

        if ($validationError !== null) {
            return $validationError;
        }

        $surface = $this->surfaceFromRequest($request);
        $user = $this->resolveSurfaceUser($request, $surface);

        if ($user instanceof JsonResponse) {
            return $user;
        }

        $verificationError = $this->ensureResetVerificationIsSatisfied($request);

        if ($verificationError !== null) {
            return $verificationError;
        }

        $this->persistResetPassword($user, $request);
        $this->clearResetArtifacts($request);

        $payload = $this->adminSurfacePayloadService->payloadFor($user, $surface);
        $payload['status'] = true;
        $payload['message'] = trans('all.message.reset_successfully');

        return new JsonResponse($payload, 201);
    }

    private function surfaceFromRequest(Request $request): string
    {
        $surface = (string) $request->route('surface', 'merchant');

        return $surface === 'platform' ? 'platform' : 'merchant';
    }

    private function resolveSurfaceUser(Request $request, string $surface): User|JsonResponse
    {
        $user = $this->resolveResetUser($request);

        if ($user === null || (int) $user->status !== Status::ACTIVE) {
            return $this->missingUserResponse($request);
        }

        if (!isset($user->roles[0]) || (int) $user->myRole === Role::CUSTOMER) {
            return response()->json([
                'errors' => ['validation' => 'Customer account cannot access admin password reset.'],
            ], 403);
        }

        if ($surface === 'platform' && (int) $user->myRole !== Role::ADMIN) {
            return response()->json([
                'errors' => ['validation' => 'Only owner-level admin accounts can use platform password reset.'],
            ], 403);
        }

        if ($surface === 'merchant') {
            if ((int) $user->myRole === Role::ADMIN) {
                return response()->json([
                    'errors' => ['validation' => 'Owner accounts must reset through the owner workspace only.'],
                ], 403);
            }

            if ($this->adminSurfacePayloadService->activeTenantMembers($user)->isEmpty()) {
                return response()->json([
                    'errors' => ['validation' => 'Merchant account is not attached to any active store.'],
                ], 403);
            }
        }

        return $user;
    }
}
