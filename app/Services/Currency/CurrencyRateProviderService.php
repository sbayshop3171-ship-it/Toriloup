<?php

namespace App\Services\Currency;

use Exception;
use Illuminate\Support\Facades\Http;

class CurrencyRateProviderService
{
    /**
     * @return array{base: string, rates: array<string, float>, source: string, synced_at: string, metadata: array<string, mixed>}
     */
    public function latest(string $baseCode): array
    {
        $baseCode = strtoupper($baseCode);
        $endpoint = str_replace('{base}', $baseCode, (string) config('currency.sync.endpoint'));
        $query = [];

        if (filled(config('currency.sync.api_key'))) {
            $query['app_id'] = config('currency.sync.api_key');
            $query['access_key'] = config('currency.sync.api_key');
        }

        $response = Http::timeout((int) config('currency.sync.timeout', 10))->get($endpoint, $query);

        if (!$response->successful()) {
            throw new Exception("Currency rate provider failed with HTTP {$response->status()}.");
        }

        $payload = $response->json();
        $rates = $payload['rates'] ?? $payload['conversion_rates'] ?? null;

        if (!is_array($rates) || $rates === []) {
            throw new Exception('Currency rate provider returned no rates.');
        }

        $normalizedRates = [];
        foreach ($rates as $code => $rate) {
            if (is_numeric($rate)) {
                $normalizedRates[strtoupper((string) $code)] = (float) $rate;
            }
        }

        $normalizedRates[$baseCode] = 1.0;

        return [
            'base' => strtoupper((string) ($payload['base_code'] ?? $payload['base'] ?? $baseCode)),
            'rates' => $normalizedRates,
            'source' => (string) config('currency.sync.driver', 'fx_provider'),
            'synced_at' => now()->toDateTimeString(),
            'metadata' => [
                'provider_result' => $payload['result'] ?? null,
                'provider_time' => $payload['time_last_update_utc'] ?? $payload['timestamp'] ?? null,
                'endpoint' => preg_replace('/([?&](app_id|access_key)=)[^&]+/i', '$1***', $endpoint),
            ],
        ];
    }
}
