<?php

namespace App\Services\Saas;

use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class PlatformAuditLogService
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $actionCode,
        string $entityType,
        ?int $entityId = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
        ?User $actor = null,
        ?Tenant $tenant = null,
        string $actorScope = 'platform',
    ): PlatformAuditLog {
        $request ??= request();
        $actor ??= $request?->user();

        return PlatformAuditLog::query()->create([
            'actor_user_id' => $actor?->id,
            'actor_scope' => $actorScope,
            'tenant_id' => $tenant?->id,
            'action_code' => $actionCode,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values_json' => $oldValues !== [] ? $oldValues : null,
            'new_values_json' => $newValues !== [] ? $newValues : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
