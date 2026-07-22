<?php

namespace App\Services\Saas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FastPanelSiteAliasService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fastpanel.base_url'))
            && filled(config('services.fastpanel.username'))
            && filled(config('services.fastpanel.password'))
            && filled(config('services.fastpanel.storefront_site_id'));
    }

    /**
     * @return array<string, mixed>
     */
    public function ensureStorefrontAlias(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);

        if (!$this->isConfigured() || $hostname === '') {
            return [
                'configured' => false,
                'ensured' => false,
                'aliases_added' => [],
                'aliases_present' => [],
                'message' => 'FastPanel storefront alias automation is not configured.',
            ];
        }

        $siteId = (string) config('services.fastpanel.storefront_site_id');
        $token = $this->login();
        $site = $this->site($siteId, $token);
        $existingAliases = $this->normalizedAliases($site['aliases'] ?? []);
        $existingNames = array_map(fn (array $alias): string => $alias['name'], $existingAliases);
        $desiredNames = $this->desiredAliasNames($hostname);
        $missingNames = array_values(array_diff($desiredNames, $existingNames));

        if ($missingNames === []) {
            return [
                'configured' => true,
                'ensured' => true,
                'site_id' => $siteId,
                'aliases_added' => [],
                'aliases_present' => $desiredNames,
                'message' => 'FastPanel storefront aliases are already present.',
            ];
        }

        $payloadAliases = [
            ...$existingAliases,
            ...array_map(fn (string $alias): array => ['name' => $alias], $missingNames),
        ];

        $response = $this->client($token)->put("/api/sites/{$siteId}", [
            'aliases' => $payloadAliases,
            'manual_changes' => false,
        ]);

        $this->throwIfFailed($response->json(), $response->failed(), 'FastPanel storefront alias update failed.');

        return [
            'configured' => true,
            'ensured' => true,
            'site_id' => $siteId,
            'aliases_added' => $missingNames,
            'aliases_present' => $desiredNames,
            'message' => 'FastPanel storefront aliases were updated.',
        ];
    }

    private function login(): string
    {
        $response = $this->client()->post('/login', [
            'username' => (string) config('services.fastpanel.username'),
            'password' => (string) config('services.fastpanel.password'),
        ]);

        $json = $response->json();
        $this->throwIfFailed($json, $response->failed(), 'FastPanel login failed.');

        $token = (string) (data_get($json, 'token')
            ?: data_get($json, 'access_token')
            ?: data_get($json, 'data.token'));

        if ($token === '') {
            throw ValidationException::withMessages([
                'fastpanel' => 'FastPanel login succeeded, but no API token was returned.',
            ]);
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function site(string $siteId, string $token): array
    {
        $response = $this->client($token)->get("/api/sites/{$siteId}");
        $json = $response->json();

        $this->throwIfFailed($json, $response->failed(), 'FastPanel storefront site lookup failed.');

        $site = data_get($json, 'data', $json);

        return is_array($site) ? $site : [];
    }

    /**
     * @param  array<int, mixed>  $aliases
     * @return array<int, array<string, mixed>>
     */
    private function normalizedAliases(array $aliases): array
    {
        return array_values(array_filter(array_map(function (mixed $alias): ?array {
            $name = is_array($alias)
                ? $this->normalizeHostname((string) ($alias['raw_name'] ?? $alias['name'] ?? ''))
                : $this->normalizeHostname((string) $alias);

            if ($name === '') {
                return null;
            }

            $normalized = ['name' => $name];
            $id = is_array($alias) ? ($alias['id'] ?? null) : null;

            if ($id !== null) {
                $normalized['id'] = $id;
            }

            return $normalized;
        }, $aliases)));
    }

    /**
     * @return array<int, string>
     */
    private function desiredAliasNames(string $hostname): array
    {
        $aliases = [$hostname];

        if (
            (bool) config('services.fastpanel.include_www_alias', true)
            && !str_starts_with($hostname, 'www.')
            && !str_starts_with($hostname, '*.')
        ) {
            $aliases[] = 'www.'.$hostname;
        }

        return array_values(array_unique($aliases));
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function throwIfFailed(?array $json, bool $failed, string $fallbackMessage): void
    {
        if (!$failed && ($json['success'] ?? true) !== false) {
            return;
        }

        $message = collect(Arr::wrap($json['errors'] ?? []))
            ->map(fn (mixed $error): ?string => is_array($error) ? ($error['message'] ?? null) : (string) $error)
            ->filter()
            ->implode(' ');

        throw ValidationException::withMessages([
            'fastpanel' => $message !== '' ? $message : $fallbackMessage,
        ]);
    }

    private function client(?string $token = null): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.fastpanel.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.fastpanel.timeout', 15));

        if (!(bool) config('services.fastpanel.verify_tls', true)) {
            $request = $request->withoutVerifying();
        }

        return $token !== null ? $request->withToken($token) : $request;
    }

    private function normalizeHostname(string $value): string
    {
        $value = trim(strtolower($value));

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $value = (string) parse_url($value, PHP_URL_HOST);
        }

        $value = preg_replace('/\/.*$/', '', $value) ?? $value;
        $value = preg_replace('/:\d+$/', '', $value) ?? $value;

        return trim($value, '. ');
    }
}
