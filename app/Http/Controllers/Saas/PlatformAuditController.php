<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = PlatformAuditLog::query()
            ->with(['actor', 'tenant'])
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', (int) $request->integer('tenant_id')))
            ->when($request->filled('action_code'), fn ($query) => $query->where('action_code', $request->string('action_code')))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q').'%';

                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('action_code', 'like', $term)
                        ->orWhere('entity_type', 'like', $term)
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', $term)->orWhere('slug', 'like', $term))
                        ->orWhereHas('actor', fn ($actorQuery) => $actorQuery->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('id')
            ->limit(min(max((int) $request->integer('limit', 50), 1), 100))
            ->get();

        return response()->json([
            'status' => true,
            'data' => $logs->map(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'actor_scope' => $log->actor_scope,
                'action_code' => $log->action_code,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
                'actor' => $log->actor?->only(['id', 'name', 'email']),
                'tenant' => $log->tenant?->only(['id', 'name', 'slug', 'status']),
            ])->values(),
        ]);
    }
}
