<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationInboxController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery($request)->latest('id');

        if ((int) $request->get('paginate', 0) === 1) {
            return UserNotificationResource::collection(
                $query->paginate(min(max((int) $request->get('per_page', 15), 1), 100))
            );
        }

        return UserNotificationResource::collection(
            $query->limit(min(max((int) $request->get('limit', 30), 1), 100))->get()
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'count' => $this->baseQuery($request)->unread()->count(),
            ],
        ]);
    }

    public function markAsRead(Request $request, UserNotification $notification): UserNotificationResource|JsonResponse
    {
        $notification = $this->baseQuery($request)
            ->whereKey($notification->id)
            ->first();

        if (!$notification instanceof UserNotification) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return new UserNotificationResource($notification->fresh());
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $this->baseQuery($request)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'status' => true,
            ],
        ]);
    }

    private function baseQuery(Request $request)
    {
        $query = UserNotification::query()
            ->where('user_id', $request->user()->id);

        $tenantId = app(TenantContext::class)->currentId($request);

        if ($tenantId !== null) {
            $query->where(function ($builder) use ($tenantId) {
                $builder->whereNull('tenant_id')
                    ->orWhere('tenant_id', $tenantId);
            });
        }

        return $query;
    }
}
