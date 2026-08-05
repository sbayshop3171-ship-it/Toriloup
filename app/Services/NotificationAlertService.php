<?php

namespace App\Services;

use App\Libraries\QueryExceptionLibrary;
use Exception;
use Illuminate\Http\Request;
use App\Models\NotificationAlert;
use Illuminate\Support\Facades\Log;

class NotificationAlertService
{
    /**
     * @throws Exception
     */
    public function list(): \Illuminate\Database\Eloquent\Collection
    {
        try {
            return NotificationAlert::orderBy('id')->get();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @throws Exception
     */
    public function update(Request $request): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $type        = $request->type;
            $alertIds = NotificationAlert::orderBy('id')->pluck('id');

            foreach ($alertIds as $alertId) {
                if (!$request->has((string) $alertId)) {
                    continue;
                }

                NotificationAlert::where('id', $alertId)->update([
                    $type . '_message' => $request->input((string) $alertId),
                    $type              => $request->input($type . $alertId),
                ]);
            }
            return $this->list();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
