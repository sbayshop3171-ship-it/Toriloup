<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverseGeocodingService
{
    private const LOCATION_TYPES = [
        'address' => 100,
        'poi' => 90,
        'neighborhood' => 70,
        'locality' => 60,
        'place' => 50,
        'postcode' => 40,
        'district' => 30,
        'region' => 20,
        'country' => 10,
    ];

    public function reverse(float $latitude, float $longitude, ?string $countryCode = null): ?array
    {
        $countryCode = $this->normalizeCountryCode($countryCode);
        $cacheKey = 'reverse-geocode:'.sha1(
            number_format($latitude, 5, '.', '').':'.
            number_format($longitude, 5, '.', '').':'.
            ($countryCode ?: '*')
        );

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($latitude, $longitude, $countryCode) {
            return $this->reverseViaMapbox($latitude, $longitude, $countryCode)
                ?: $this->reverseViaNominatim($latitude, $longitude, $countryCode);
        });
    }

    private function reverseViaMapbox(float $latitude, float $longitude, ?string $countryCode): ?array
    {
        $accessToken = trim((string) config('services.mapbox.access_token'));
        if ($accessToken === '') {
            return null;
        }

        try {
            $params = [
                'access_token' => $accessToken,
                'language' => 'en',
                'types' => 'address,poi,neighborhood,place,postcode,locality,district,region,country',
                'limit' => 5,
            ];

            if ($countryCode) {
                $params['country'] = strtolower($countryCode);
            }

            $response = Http::timeout(5)
                ->acceptJson()
                ->get(
                    'https://api.mapbox.com/geocoding/v5/mapbox.places/'.
                    $this->coordinate($longitude).','.$this->coordinate($latitude).'.json',
                    $params
                );

            if (!$response->ok()) {
                return null;
            }

            $features = $response->json('features');
            if (!is_array($features) || count($features) === 0) {
                return null;
            }

            usort($features, fn (array $left, array $right) => $this->featurePriority($right) <=> $this->featurePriority($left));

            return $this->mapMapboxFeature($features[0], $latitude, $longitude);
        } catch (Throwable $exception) {
            Log::debug('Mapbox reverse geocode failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function reverseViaNominatim(float $latitude, float $longitude, ?string $countryCode): ?array
    {
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'Toriloup/1.0 ('.config('app.url').')',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $this->coordinate($latitude),
                    'lon' => $this->coordinate($longitude),
                    'addressdetails' => 1,
                    'accept-language' => 'en',
                    'zoom' => 18,
                ]);

            if (!$response->ok()) {
                return null;
            }

            $payload = $response->json();
            if (!is_array($payload)) {
                return null;
            }

            $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
            $detectedCountryCode = $this->normalizeCountryCode($address['country_code'] ?? null);

            if ($countryCode && $detectedCountryCode && $countryCode !== $detectedCountryCode) {
                return null;
            }

            $streetAddress = $this->composeNominatimAddress($address, $payload['display_name'] ?? null);

            return [
                'id' => $payload['osm_id'] ?? null,
                'label' => $payload['display_name'] ?? $streetAddress,
                'street_address' => $streetAddress,
                'country' => $this->stringOrNull($address['country'] ?? null),
                'country_code' => $detectedCountryCode,
                'state' => $this->firstString($address, ['state', 'region', 'state_district']),
                'city' => $this->firstString($address, ['city', 'town', 'municipality', 'village', 'county', 'city_district']),
                'zip_code' => $this->stringOrNull($address['postcode'] ?? null),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source' => 'nominatim',
            ];
        } catch (Throwable $exception) {
            Log::debug('Nominatim reverse geocode failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function mapMapboxFeature(array $feature, float $latitude, float $longitude): ?array
    {
        $label = $this->stringOrNull($feature['place_name'] ?? null) ?: $this->stringOrNull($feature['text'] ?? null);
        if (!$label) {
            return null;
        }

        return [
            'id' => $feature['id'] ?? null,
            'label' => $label,
            'street_address' => $label,
            'country' => $this->resolveMapboxText($feature, 'country'),
            'country_code' => $this->resolveMapboxCountryCode($feature),
            'state' => $this->resolveMapboxText($feature, 'region'),
            'city' => $this->resolveMapboxCity($feature),
            'zip_code' => $this->resolveMapboxText($feature, 'postcode'),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'source' => 'mapbox',
        ];
    }

    private function resolveMapboxCity(array $feature): ?string
    {
        return $this->resolveMapboxText($feature, 'place')
            ?: $this->resolveMapboxText($feature, 'locality')
            ?: $this->resolveMapboxText($feature, 'district');
    }

    private function resolveMapboxCountryCode(array $feature): ?string
    {
        $country = $this->featureTypeValue($feature, 'country');

        return $this->normalizeCountryCode($country['short_code'] ?? null);
    }

    private function resolveMapboxText(array $feature, string $type): ?string
    {
        $value = $this->featureTypeValue($feature, $type);

        return $this->stringOrNull($value['text'] ?? null);
    }

    private function featureTypeValue(array $feature, string $type): ?array
    {
        $placeTypes = is_array($feature['place_type'] ?? null) ? $feature['place_type'] : [];
        if (in_array($type, $placeTypes, true)) {
            return $feature;
        }

        $context = is_array($feature['context'] ?? null) ? $feature['context'] : [];
        foreach ($context as $item) {
            if (is_array($item) && isset($item['id']) && strpos((string) $item['id'], $type.'.') === 0) {
                return $item;
            }
        }

        return null;
    }

    private function featurePriority(array $feature): int
    {
        $placeTypes = is_array($feature['place_type'] ?? null) ? $feature['place_type'] : [];
        $score = 0;

        foreach ($placeTypes as $type) {
            $score = max($score, self::LOCATION_TYPES[$type] ?? 0);
        }

        return $score
            + (!empty($feature['address']) ? 8 : 0)
            + ($this->resolveMapboxText($feature, 'postcode') ? 3 : 0)
            + ($this->resolveMapboxCity($feature) ? 2 : 0)
            + ($this->resolveMapboxText($feature, 'region') ? 1 : 0);
    }

    private function composeNominatimAddress(array $address, ?string $displayName): string
    {
        $roadLine = trim(implode(' ', array_filter([
            $this->stringOrNull($address['house_number'] ?? null),
            $this->stringOrNull($address['road'] ?? null),
        ])));

        $parts = [
            $roadLine,
            $this->firstString($address, ['neighbourhood', 'suburb', 'quarter', 'city_district']),
            $this->firstString($address, ['city', 'town', 'municipality', 'village']),
            $this->firstString($address, ['state', 'region', 'state_district']),
            $this->stringOrNull($address['country'] ?? null),
            $this->stringOrNull($address['postcode'] ?? null),
        ];

        $line = implode(', ', $this->uniqueParts($parts));

        return $line !== '' ? $line : (string) $displayName;
    }

    private function uniqueParts(array $parts): array
    {
        $seen = [];
        $unique = [];

        foreach ($parts as $part) {
            $value = $this->stringOrNull($part);
            if (!$value) {
                continue;
            }

            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $value));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $value;
        }

        return $unique;
    }

    private function firstString(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->stringOrNull($source[$key] ?? null);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeCountryCode(mixed $countryCode): ?string
    {
        $countryCode = strtoupper(trim((string) $countryCode));

        return strlen($countryCode) === 2 ? $countryCode : null;
    }

    private function coordinate(float $coordinate): string
    {
        return rtrim(rtrim(number_format($coordinate, 7, '.', ''), '0'), '.');
    }
}
