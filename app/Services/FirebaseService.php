<?php

namespace App\Services;


use Exception;
use GuzzleHttp\Client;
use App\Models\NotificationSetting;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;
use Google\Auth\Credentials\ServiceAccountCredentials;

class FirebaseService
{
    public $filePath;

    public function sendNotification($data, $fcmTokens, $topicName)
    {

        try {
            $notification = Settings::group('notification')->all();
            $projectId = $notification['notification_fcm_project_id'] ?? null;

            if (blank($projectId)) {
                throw new Exception('Firebase project id is not configured');
            }

            $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
            $accessToken = $this->getAccessToken();

            $client  = new Client();
            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ];
            foreach ($fcmTokens as $fcmToken) {
                $dataPayload = $this->dataPayload($data, $topicName);
                $notificationPayload = [
                    'title' => $data->title,
                    'body'  => $data->description,
                ];

                if (!blank($data->image ?? null)) {
                    $notificationPayload['image'] = $data->image;
                }

                $payload = [
                    'message' => [
                        'token' => $fcmToken,
                        'notification' => $notificationPayload,
                        'data' => $dataPayload,
                        'webpush' => [
                            "headers" => [
                                "Urgency" => "high"
                            ]
                        ],
                    ],
                ];

                try {
                    $client->post($url, [
                    'headers' => $headers,
                    "body"    => json_encode($payload)
                ]);
                } catch (\Throwable $th) {
                   
                }
            }
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return $e->getMessage();
        }
    }

    private function dataPayload($data, $topicName): array
    {
        $targetType = $data->target_type ?? $this->targetTypeFromTopic($topicName);
        $targetValue = $data->target_value ?? null;
        $orderId = $data->order_id ?? null;

        if (!blank($orderId)) {
            $targetType = 'order';
            $targetValue = (string)$orderId;
        }

        $payload = [
            'title'        => (string)($data->title ?? ''),
            'body'         => (string)($data->description ?? ''),
            'sound'        => 'default',
            'image'        => (string)($data->image ?? ''),
            'topicName'    => (string)$topicName,
            'topic'        => (string)$topicName,
            'target_type'  => (string)($targetType ?? 'home'),
            'target_value' => (string)($targetValue ?? ''),
            'route'        => (string)($targetType ?? 'home'),
        ];

        foreach (['route_url', 'notification_id', 'event_key', 'audience', 'tenant_id'] as $key) {
            if (!blank($data->{$key} ?? null)) {
                $payload[$key] = (string) $data->{$key};
            }
        }

        foreach (($data->data ?? []) as $key => $value) {
            if (!array_key_exists((string) $key, $payload) && (is_scalar($value) || $value === null)) {
                $payload[(string) $key] = (string) ($value ?? '');
            }
        }

        if (!blank($orderId)) {
            $payload['order_id'] = (string)$orderId;
            $payload['target_id'] = (string)$orderId;
        }

        return $payload;
    }

    private function targetTypeFromTopic($topicName): string
    {
        $topic = str_replace('-', '_', strtolower((string)$topicName));

        if (str_contains($topic, 'order')) {
            return 'order';
        }

        if (in_array($topic, ['product', 'category', 'promotion', 'cart', 'wishlist', 'profile', 'orders', 'home'])) {
            return $topic;
        }

        return 'home';
    }

    function getAccessToken()
    {

        $notificationSetting = NotificationSetting::where(['key' => 'notification_fcm_json_file'])->first();

        if (!$notificationSetting || blank($notificationSetting->file)) {
            throw new Exception('Firebase service account JSON file is not configured');
        }

        $keyFilePath = $notificationSetting->file;
        $parsed_url = parse_url($keyFilePath);

        if (isset($parsed_url['path'])) {
            $relative_path = ltrim($parsed_url['path'], '/storage');
            $this->filePath = storage_path('app/public/' . $relative_path);
        } else {
            throw new Exception('No file found in the URL');
        }

        $SCOPES = ['https://www.googleapis.com/auth/cloud-platform'];

        if (!file_exists($this->filePath)) {
            throw new Exception('Service account key file not found');
        }

        $credentials = new ServiceAccountCredentials($SCOPES, $this->filePath);
        $token = $credentials->fetchAuthToken();

        if (isset($token['access_token'])) {
            return $token['access_token'];
        } else {
            throw new Exception('Failed to fetch access token');
        }
    }
}
