<?php

namespace App\Services\Saas;

use App\Models\PlatformSupportSession;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class PlatformSupportSessionService
{
    public function __construct(
        private readonly PlatformAuditLogService $platformAuditLogService,
        private readonly SurfaceTokenService $surfaceTokenService,
    ) {
    }

    /**
     * @return Collection<int, PlatformSupportSession>
     */
    public function list(array $filters = []): Collection
    {
        return PlatformSupportSession::query()
            ->with(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role'])
            ->when(
                filled($filters['status'] ?? null),
                fn ($query) => $query->where('status', (string) $filters['status'])
            )
            ->when(
                filled($filters['tenant_id'] ?? null),
                fn ($query) => $query->where('tenant_id', (int) $filters['tenant_id'])
            )
            ->when(filled($filters['q'] ?? null), function ($query) use ($filters): void {
                $term = '%'.trim((string) $filters['q']).'%';

                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('reason', 'like', $term)
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', $term)->orWhere('slug', 'like', $term))
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('impersonatedUser', fn ($userQuery) => $userQuery->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('id')
            ->limit(min(max((int) ($filters['limit'] ?? 50), 1), 100))
            ->get()
            ->map(function (PlatformSupportSession $session) {
                return $this->expireIfNeeded($session);
            });
    }

    public function start(Tenant $tenant, User $owner, ?string $reason = null, ?Request $request = null): PlatformSupportSession
    {
        $tenant->loadMissing([
            'members.user',
            'members.role',
        ]);

        $member = $this->defaultMembershipForTenant($tenant);

        if ($member === null || $member->user === null) {
            throw ValidationException::withMessages([
                'tenant_id' => 'No active merchant member is available for this tenant support session.',
            ]);
        }

        $session = PlatformSupportSession::query()->create([
            'tenant_id' => $tenant->id,
            'owner_user_id' => $owner->id,
            'impersonated_user_id' => $member->user_id,
            'tenant_member_id' => $member->id,
            'status' => 'pending',
            'handoff_code' => Str::random(48),
            'reason' => filled($reason) ? trim((string) $reason) : null,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->platformAuditLogService->log(
            'platform.support.impersonation.started',
            'platform_support_session',
            $session->id,
            [],
            [
                'tenant_id' => $tenant->id,
                'impersonated_user_id' => $member->user_id,
                'tenant_member_id' => $member->id,
                'expires_at' => $session->expires_at,
                'reason' => $session->reason,
            ],
            $request,
            $owner,
            $tenant
        );

        return $session->fresh(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role']);
    }

    public function consumeByHandoffCode(string $handoffCode): PlatformSupportSession
    {
        $session = PlatformSupportSession::query()
            ->with(['tenant.domains', 'owner', 'impersonatedUser.roles', 'tenantMember.tenant.domains', 'tenantMember.role'])
            ->where('handoff_code', $handoffCode)
            ->firstOrFail();

        $session = $this->expireIfNeeded($session);

        if ($session->status === 'ended' || $session->status === 'expired') {
            throw ValidationException::withMessages([
                'handoff_code' => 'This support session is no longer available.',
            ]);
        }

        if ($session->impersonatedUser === null || $session->tenantMember === null) {
            throw ValidationException::withMessages([
                'handoff_code' => 'This support session is missing merchant access context.',
            ]);
        }

        $this->deleteSessionToken($session);

        $newAccessToken = $this->surfaceTokenService->createToken($session->impersonatedUser, 'merchant');

        $session->forceFill([
            'status' => 'active',
            'merchant_token_id' => $newAccessToken->accessToken->id,
            'consumed_at' => now(),
        ])->save();
        $session->setAttribute('merchant_plain_text_token', $newAccessToken->plainTextToken);

        $this->platformAuditLogService->log(
            'platform.support.impersonation.consumed',
            'platform_support_session',
            $session->id,
            [],
            [
                'tenant_id' => $session->tenant_id,
                'impersonated_user_id' => $session->impersonated_user_id,
                'merchant_token_id' => $session->merchant_token_id,
                'consumed_at' => $session->consumed_at,
            ],
            null,
            $session->owner,
            $session->tenant
        );

        return tap(
            $session->fresh(['tenant', 'owner', 'impersonatedUser', 'tenantMember.tenant.domains', 'tenantMember.role']),
            fn (PlatformSupportSession $freshSession) => $freshSession->setAttribute('merchant_plain_text_token', $newAccessToken->plainTextToken)
        );
    }

    public function endByOwner(PlatformSupportSession|int $session, ?User $owner = null, ?Request $request = null): PlatformSupportSession
    {
        $session = $session instanceof PlatformSupportSession
            ? $session->loadMissing(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role'])
            : PlatformSupportSession::query()
                ->with(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role'])
                ->findOrFail($session);

        return $this->finish($session, $owner, $request, 'platform.support.impersonation.ended');
    }

    public function endByMerchant(int $sessionId, User $merchantUser, ?Request $request = null): PlatformSupportSession
    {
        $session = PlatformSupportSession::query()
            ->with(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role'])
            ->findOrFail($sessionId);

        if ((int) $session->impersonated_user_id !== (int) $merchantUser->id) {
            throw ValidationException::withMessages([
                'session' => 'You cannot close another merchant support session.',
            ]);
        }

        return $this->finish($session, $merchantUser, $request, 'platform.support.impersonation.merchant-exit');
    }

    public function currentForToken(?User $user, ?int $tokenId): ?PlatformSupportSession
    {
        if ($user === null || $tokenId === null) {
            return null;
        }

        $session = PlatformSupportSession::query()
            ->with(['tenant', 'owner', 'impersonatedUser', 'tenantMember.tenant.domains', 'tenantMember.role'])
            ->where('impersonated_user_id', $user->id)
            ->where('merchant_token_id', $tokenId)
            ->first();

        if ($session === null) {
            return null;
        }

        $session = $this->expireIfNeeded($session);

        if (!in_array($session->status, ['pending', 'active'], true)) {
            return null;
        }

        return $session;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSession(PlatformSupportSession $session, bool $includeLaunchUrl = false): array
    {
        $session->loadMissing(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role']);

        $payload = [
            'id' => $session->id,
            'status' => $session->status,
            'reason' => $session->reason,
            'started_at' => $session->started_at,
            'consumed_at' => $session->consumed_at,
            'expires_at' => $session->expires_at,
            'ended_at' => $session->ended_at,
            'tenant' => $session->tenant?->only(['id', 'name', 'slug', 'status', 'plan_code']),
            'owner' => $session->owner?->only(['id', 'name', 'email']),
            'impersonated_user' => $session->impersonatedUser?->only(['id', 'name', 'email']),
            'tenant_member' => $session->tenantMember ? [
                'id' => $session->tenantMember->id,
                'status' => $session->tenantMember->status,
                'role' => $session->tenantMember->role?->only(['id', 'code', 'name', 'scope']),
            ] : null,
        ];

        if ($includeLaunchUrl) {
            $merchantHost = rtrim((string) config('saas.merchant_host'), '/');
            $payload['launch_url'] = $merchantHost !== ''
                ? 'https://'.$merchantHost.'/support/session/'.$session->handoff_code
                : null;
        }

        return $payload;
    }

    private function finish(PlatformSupportSession $session, ?User $actor, ?Request $request, string $actionCode): PlatformSupportSession
    {
        $session = $this->expireIfNeeded($session);

        if (in_array($session->status, ['ended', 'expired'], true)) {
            return $session;
        }

        $oldValues = $session->only(['status', 'ended_at', 'merchant_token_id']);
        $this->deleteSessionToken($session);

        $session->forceFill([
            'status' => 'ended',
            'ended_at' => now(),
            'merchant_token_id' => null,
        ])->save();

        $this->platformAuditLogService->log(
            $actionCode,
            'platform_support_session',
            $session->id,
            $oldValues,
            $session->only(array_keys($oldValues)),
            $request,
            $actor,
            $session->tenant
        );

        return $session->fresh(['tenant', 'owner', 'impersonatedUser', 'tenantMember.role']);
    }

    private function deleteSessionToken(PlatformSupportSession $session): void
    {
        if (!$session->merchant_token_id) {
            return;
        }

        PersonalAccessToken::query()->whereKey($session->merchant_token_id)->delete();
    }

    private function expireIfNeeded(PlatformSupportSession $session): PlatformSupportSession
    {
        if ($session->status !== 'expired' && $session->ended_at === null && $session->expires_at !== null && $session->expires_at->isPast()) {
            $oldValues = $session->only(['status', 'ended_at', 'merchant_token_id']);
            $this->deleteSessionToken($session);

            $session->forceFill([
                'status' => 'expired',
                'ended_at' => $session->ended_at ?? now(),
                'merchant_token_id' => null,
            ])->save();

            $this->platformAuditLogService->log(
                'platform.support.impersonation.expired',
                'platform_support_session',
                $session->id,
                $oldValues,
                $session->only(array_keys($oldValues)),
                null,
                $session->owner,
                $session->tenant
            );
        }

        return $session;
    }

    private function defaultMembershipForTenant(Tenant $tenant): ?TenantMember
    {
        return $tenant->members
            ->where('status', 'active')
            ->filter(fn (TenantMember $member) => $member->user !== null)
            ->sortBy(function (TenantMember $member): string {
                return $member->role?->code === 'merchant_owner'
                    ? '0_'.$member->id
                    : '1_'.$member->id;
            })
            ->first();
    }
}
