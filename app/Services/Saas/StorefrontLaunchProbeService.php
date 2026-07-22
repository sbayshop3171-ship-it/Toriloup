<?php

namespace App\Services\Saas;

use App\Models\TenantDomain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StorefrontLaunchProbeService
{
    /**
     * @return array<string, mixed>
     */
    public function probe(TenantDomain $domain): array
    {
        $hostname = trim(strtolower((string) $domain->hostname), '. ');
        $url = sprintf('https://%s/api/storefront/up', $hostname);

        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->withoutRedirecting()
                ->withHeaders([
                    'x-api-key' => $this->apiKey(),
                    'x-localization' => 'en',
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            return [
                'launched' => false,
                'check_type' => 'storefront_probe',
                'message' => 'DNS is connected, but this domain is not serving the Toriloup storefront yet. Finish the domain/server connection, then try again.',
                'payload_json' => [
                    'probe_url' => $url,
                    'error' => $exception->getMessage(),
                ],
            ];
        }

        $contentType = strtolower((string) $response->header('content-type', ''));
        $payload = $response->json();
        $isJson = is_array($payload) || str_contains($contentType, 'application/json');
        $surface = is_array($payload) ? (string) ($payload['surface'] ?? '') : '';
        $scaffold = is_array($payload) ? (string) ($payload['scaffold'] ?? '') : '';

        if (
            $response->ok()
            && $isJson
            && $surface === 'storefront'
            && $scaffold === 'storefront'
        ) {
            return [
                'launched' => true,
                'check_type' => 'storefront_probe',
                'message' => 'Domain is serving the Toriloup storefront and is ready to launch.',
                'payload_json' => [
                    'probe_url' => $url,
                    'status' => $response->status(),
                    'surface' => $surface,
                    'scaffold' => $scaffold,
                ],
            ];
        }

        $message = match (true) {
            $response->status() >= 300 && $response->status() < 400 => 'The domain is still redirecting to another website. Point it to Toriloup first, then retry launch.',
            $response->status() === 404 => 'The domain reached a website, but not the Toriloup storefront. This usually means the domain is still attached to another site on the server.',
            default => 'DNS exists, but this domain is not live on the Toriloup storefront yet.',
        };

        return [
            'launched' => false,
            'check_type' => 'storefront_probe',
            'message' => $message,
            'payload_json' => [
                'probe_url' => $url,
                'status' => $response->status(),
                'content_type' => $contentType,
                'body_excerpt' => mb_substr((string) $response->body(), 0, 300),
            ],
        ];
    }

    private function apiKey(): string
    {
        return (string) (data_get(config('installer'), 'buildPayload.license_code') ?: env('VITE_API_KEY', ''));
    }
}
