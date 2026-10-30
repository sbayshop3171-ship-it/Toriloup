<?php

namespace App\Services;


use Exception;
use App\Models\User;
use App\Models\PushDeviceToken;
use App\Models\PushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\PaginateRequest;
use App\Http\Requests\PushNotificationRequest;
use App\Libraries\QueryExceptionLibrary;

class PushNotificationService
{
    public object $pushNotification;
    protected array $pushNotificationFilter = [
        'title',
        'role_id',
        'user_id',
        'target_type'
    ];

    /**
     * @throws Exception
     */
    public function list(PaginateRequest $request)
    {
        try {
            $requests    = $request->all();
            $method      = $request->get('paginate', 0) == 1 ? 'paginate' : 'get';
            $methodValue = $request->get('paginate', 0) == 1 ? $request->get('per_page', 10) : '*';
            $orderColumn = $request->get('order_column') ?? 'id';
            $orderType   = $request->get('order_type') ?? 'desc';

            return PushNotification::where(function ($query) use ($requests) {
                foreach ($requests as $key => $request) {
                    if (in_array($key, $this->pushNotificationFilter)) {
                        $query->where($key, 'like', '%' . $request . '%');
                    }
                }
            })->orderBy($orderColumn, $orderType)->$method(
                $methodValue
            );
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function store(PushNotificationRequest $request): PushNotification
    {
        try {
            $pushNotification              = new PushNotification();
            $pushNotification->title       = $request->title;
            $pushNotification->description = strip_tags($request->description);
            $pushNotification->role_id     = $request->role_id ?? 0;
            $pushNotification->user_id     = $request->user_id ?? 0;
            $pushNotification->target_type  = $request->target_type ?? 'home';
            $pushNotification->target_value = $request->target_value;
            $pushNotification->save();

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $pushNotification->clearMediaCollection('pushNotifications');
                $pushNotification->addMediaFromRequest('image')->toMediaCollection('pushNotifications');
            }

            $recipients = User::query();
            if ($pushNotification->role_id != 0 && $pushNotification->user_id == 0) {
                $recipients->role($pushNotification->role_id);
            } elseif ($pushNotification->user_id != 0) {
                $recipients->whereKey($pushNotification->user_id);
            }

            $recipientIds = $recipients->pluck('id');
            $fcmTokenArray = PushDeviceToken::query()
                ->whereIn('user_id', $recipientIds)
                ->pluck('token')
                ->merge(User::query()->whereIn('id', $recipientIds)->pluck('web_token'))
                ->merge(User::query()->whereIn('id', $recipientIds)->pluck('device_token'))
                ->filter(fn ($token): bool => is_string($token) && trim($token) !== '')
                ->unique()
                ->values()
                ->all();
            $firebase      = new FirebaseService();
            $firebase->sendNotification($pushNotification, $fcmTokenArray, $pushNotification->target_type ?? "home");
            return $pushNotification;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function show(PushNotification $pushNotification): PushNotification
    {
        try {
            return $pushNotification;
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function destroy(PushNotification $pushNotification)
    {
        try {
            DB::transaction(function () use ($pushNotification) {
                $pushNotification->delete();
            });
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            DB::rollBack();
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
