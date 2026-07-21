<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PragmaRX\Countries\Package\Countries;
use Throwable;

class IpLocationService
{
    public function detect(Request $request): ?array
    {
        $ip = $this->clientIp($request);

        if (!$this->isPublicIp($ip)) {
            return null;
        }

        return Cache::remember('ip-location-country:'.sha1($ip), now()->addHours(6), function () use ($ip) {
            return $this->lookup($ip);
        });
    }

    private function clientIp(Request $request): ?string
    {
        $candidates = [
            $request->headers->get('CF-Connecting-IP'),
            $request->headers->get('X-Real-IP'),
            explode(',', (string) $request->headers->get('X-Forwarded-For'))[0] ?? null,
            $request->ip(),
        ];

        foreach ($candidates as $candidate) {
            $ip = trim((string) $candidate);
            if ($this->isPublicIp($ip)) {
                return $ip;
            }
        }

        return trim((string) $request->ip()) ?: null;
    }

    private function isPublicIp(?string $ip): bool
    {
        return is_string($ip)
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function lookup(string $ip): ?array
    {
        foreach ($this->providers($ip) as $provider) {
            try {
                $response = Http::timeout(2)->acceptJson()->get($provider['url']);

                if (!$response->ok()) {
                    continue;
                }

                $location = $provider['map']($response->json());
                if ($location !== null) {
                    return $this->hydrateCountry($location, $provider['source']);
                }
            } catch (Throwable $exception) {
                Log::debug('IP location lookup failed', [
                    'provider' => $provider['source'],
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function providers(string $ip): array
    {
        return [
            [
                'source' => 'ipapi.co',
                'url' => "https://ipapi.co/{$ip}/json/",
                'map' => function (array $payload): ?array {
                    return [
                        'country_code' => $payload['country_code'] ?? null,
                        'country_name' => $payload['country_name'] ?? null,
                        'state' => $payload['region'] ?? null,
                        'city' => $payload['city'] ?? null,
                        'zip_code' => $payload['postal'] ?? null,
                        'latitude' => $payload['latitude'] ?? null,
                        'longitude' => $payload['longitude'] ?? null,
                    ];
                },
            ],
            [
                'source' => 'ipwho.is',
                'url' => "https://ipwho.is/{$ip}",
                'map' => function (array $payload): ?array {
                    if (($payload['success'] ?? true) === false) {
                        return null;
                    }

                    return [
                        'country_code' => $payload['country_code'] ?? null,
                        'country_name' => $payload['country'] ?? null,
                        'state' => $payload['region'] ?? null,
                        'city' => $payload['city'] ?? null,
                        'zip_code' => $payload['postal'] ?? null,
                        'latitude' => $payload['latitude'] ?? null,
                        'longitude' => $payload['longitude'] ?? null,
                    ];
                },
            ],
        ];
    }

    private function hydrateCountry(array $location, string $source): ?array
    {
        $countryCode = strtoupper(trim((string) ($location['country_code'] ?? '')));
        if (strlen($countryCode) !== 2) {
            return null;
        }

        $country = Country::query()
            ->where('code', $countryCode)
            ->where('status', Status::ACTIVE)
            ->first();

        $packageCountry = $this->packageCountry($countryCode);
        $callingCode = $this->normalizeCallingCode(data_get($packageCountry, 'calling_codes.0'));

        return [
            'country_code' => $countryCode,
            'country_name' => $country?->name ?: ($location['country_name'] ?? data_get($packageCountry, 'admin')),
            'calling_code' => $callingCode,
            'flag_emoji' => data_get($packageCountry, 'extra.emoji'),
            'state' => $location['state'] ?? null,
            'city' => $location['city'] ?? null,
            'zip_code' => $location['zip_code'] ?? null,
            'latitude' => is_numeric($location['latitude'] ?? null) ? (float) $location['latitude'] : null,
            'longitude' => is_numeric($location['longitude'] ?? null) ? (float) $location['longitude'] : null,
            'source' => $source,
        ];
    }

    private function normalizeCallingCode(?string $callingCode): ?string
    {
        return match ($callingCode) {
            '+1201' => '+1',
            '+73' => '+7',
            default => $callingCode,
        };
    }

    private function packageCountry(string $countryCode): mixed
    {
        foreach (Countries::all() as $key => $country) {
            if (strtoupper((string) $key) === $countryCode || strtoupper((string) data_get($country, 'cca2')) === $countryCode) {
                return $country;
            }
        }

        return null;
    }
}
