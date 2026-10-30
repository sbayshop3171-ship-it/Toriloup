<?php

namespace App\Http\Resources;

use App\Services\FirebaseServiceAccountService;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{

    public $info;

    public function __construct($info)
    {
        parent::__construct($info);
        $this->info = $info;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            "notification_fcm_public_vapid_key"    => $this->info['notification_fcm_public_vapid_key'] ?? null,
            "notification_fcm_api_key"             => $this->info['notification_fcm_api_key'] ?? null,
            "notification_fcm_auth_domain"         => $this->info['notification_fcm_auth_domain'] ?? null,
            "notification_fcm_project_id"          => $this->info['notification_fcm_project_id'] ?? null,
            "notification_fcm_storage_bucket"      => $this->info['notification_fcm_storage_bucket'] ?? null,
            "notification_fcm_messaging_sender_id" => $this->info['notification_fcm_messaging_sender_id'] ?? null,
            "notification_fcm_app_id"              => $this->info['notification_fcm_app_id'] ?? null,
            "notification_fcm_android_app_id"       => $this->info['notification_fcm_android_app_id'] ?? null,
            "notification_fcm_measurement_id"      => $this->info['notification_fcm_measurement_id'] ?? null,
            "notification_fcm_service_account_configured" => app(FirebaseServiceAccountService::class)->isConfigured(),
        ];
    }
}
