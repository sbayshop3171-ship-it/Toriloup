<?php

namespace App\Services;


use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Dipokhalder\Settings\Facades\Settings;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Throwable;

class FirebaseService
{
    public function sendNotification($data, $fcmTokens, $topicName)
    {
        try {
            $notification = Settings::group('notification')->all();
            $projectId = $notification['notification_fcm_project_id'] ?? null;

            if (blank($projectId)) {
                throw new Exception('Firebase project id is not configured');
            }

            if (empty($fcmTokens)) {
                return false;
            }

            $url = 'https://fcm.googleapis.com/v1/projects/' . $projectId . '/messages:send';
            $accessToken = $this->getAccessToken();

            $client  = new Client(['connect_timeout' => 5, 'timeout' => 12]);
            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type'  => 'application/json',
            ];
            $sent = false;
            foreach (array_unique($fcmTokens) as $fcmToken) {
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
                        'json' => $payload,
                    ]);
                    $sent = true;
                } catch (Throwable $exception) {
                    Log::warning('Firebase push delivery failed for a device token.', [
                        'status' => $exception instanceof \GuzzleHttp\Exception\RequestException
                            ? $exception->getResponse()?->getStatusCode()
                            : null,
                    ]);
                }
            }

            return $sent;
        } catch (Throwable $exception) {
            Log::warning('Firebase push is unavailable.');
            return false;
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

    public function getAccessToken()
    {
        $key = app(FirebaseServiceAccountService::class)->credentials();
        if ($key === null) {
            throw new Exception('Firebase service account JSON is not configured');
        }

        $notification = Settings::group('notification')->all();
        if (($key['project_id'] ?? null) !== ($notification['notification_fcm_project_id'] ?? null)) {
            throw new Exception('Firebase project id does not match the configured service account');
        }

        $credentials = new ServiceAccountCredentials(
            ['https://www.googleapis.com/auth/cloud-platform'],
            $key
        );
        $token = $credentials->fetchAuthToken();

        if (isset($token['access_token'])) {
            return $token['access_token'];
        } else {
            throw new Exception('Failed to fetch access token');
        }
    }
}
