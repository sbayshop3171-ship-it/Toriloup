<?php

namespace App\Services;

use App\Http\Requests\NotificationRequest;
use App\Libraries\AppLibrary;
use App\Libraries\QueryExceptionLibrary;
use Dipokhalder\EnvEditor\EnvEditor;
use Exception;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;

class NotificationService
{
    public $envService;

    public function __construct(EnvEditor $envEditor)
    {
        $this->envService = $envEditor;
    }

    /**
     * @throws Exception
     */
    public function list()
    {
        try {
            return Settings::group('notification')->all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * @param NotificationRequest $request
     * @return
     * @throws Exception
     */
    public function update(NotificationRequest $request)
    {
        try {
            if (!$this->envService->getValue('DEMO')) {
                $settings = $request->validated();
                $uploadedCredentials = null;
                if ($request->file('notification_fcm_json_file')) {
                    $uploadedCredentials = json_decode(
                        $request->file('notification_fcm_json_file')->get(),
                        true
                    );

                    if (!is_array($uploadedCredentials)
                        || ($uploadedCredentials['type'] ?? null) !== 'service_account'
                        || empty($uploadedCredentials['project_id'])
                        || empty($uploadedCredentials['client_email'])
                        || empty($uploadedCredentials['private_key'])) {
                        throw new Exception('The uploaded file is not a valid Firebase service-account JSON file.', 422);
                    }

                    if ($uploadedCredentials['project_id'] !== $settings['notification_fcm_project_id']) {
                        throw new Exception('The Firebase service account project must match the configured project ID.', 422);
                    }
                }

                unset($settings['notification_fcm_json_file']);

                AppLibrary::fcmDataBind($request);
                Settings::group('notification')->set($settings);

                if ($uploadedCredentials !== null) {
                    app(FirebaseServiceAccountService::class)->store($uploadedCredentials);
                }
                return $this->list();
            } else {
                throw new Exception(trans('all.message.feature_disable'), 422);
            }
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
