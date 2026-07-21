<?php

namespace App\Services\Saas;

use App\Models\TenantDomain;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CloudflareDnsService
{
    public function isConfigured(): bool
    {
        return filled(config('cloudflare.api_token'));
    }

    /**
     * @return array<string, mixed>
     */
    public function connectTenantDomain(TenantDomain $domain, string $target): array
    {
        if (!$this->isConfigured()) {
            throw ValidationException::withMessages([
                'cloudflare' => 'Cloudflare automatic connect is not configured yet. Add the platform Cloudflare API token first.',
            ]);
        }

        $zone = $this->findZoneForHostname($domain->hostname);

        if ($zone === null) {
            throw ValidationException::withMessages([
                'cloudflare' => 'No accessible Cloudflare zone was found for this hostname. Make sure the domain is inside the connected Cloudflare account, or add the CNAME manually and use Check DNS.',
            ]);
        }

        $zoneId = (string) ($zone['id'] ?? '');
        $records = $this->listDnsRecords($zoneId, $domain->hostname);
        $conflicts = array_values(array_filter($records, function (array $record): bool {
            return strtoupper((string) ($record['type'] ?? '')) !== 'CNAME';
        }));

        if ($conflicts !== []) {
            $types = implode(', ', array_unique(array_map(
                fn (array $record): string => strtoupper((string) ($record['type'] ?? 'UNKNOWN')),
                $conflicts
            )));

            throw ValidationException::withMessages([
                'cloudflare' => "Cannot create the CNAME automatically because {$domain->hostname} already has conflicting DNS record(s): {$types}. Remove those record(s) first, then try again.",
            ]);
        }

        $existingRecord = collect($records)->first(function (array $record) use ($domain): bool {
            return $this->normalizeHostname((string) ($record['name'] ?? '')) === $this->normalizeHostname($domain->hostname)
                && strtoupper((string) ($record['type'] ?? '')) === 'CNAME';
        });

        $payload = [
            'type' => 'CNAME',
            'name' => $domain->hostname,
            'content' => $target,
            'ttl' => 1,
            'proxied' => $this->proxyCustomDomains(),
        ];

        if ($existingRecord !== null) {
            $recordId = (string) ($existingRecord['id'] ?? '');
            $needsUpdate = $this->normalizeHostname((string) ($existingRecord['content'] ?? '')) !== $this->normalizeHostname($target)
                || (bool) ($existingRecord['proxied'] ?? false) !== $this->proxyCustomDomains();

            if ($needsUpdate) {
                $record = $this->request('put', "/zones/{$zoneId}/dns_records/{$recordId}", $payload)['result'] ?? [];
            } else {
                $record = $existingRecord;
            }
        } else {
            $record = $this->request('post', "/zones/{$zoneId}/dns_records", $payload)['result'] ?? [];
        }

        return [
            'zone_id' => $zoneId,
            'zone_name' => $zone['name'] ?? null,
            'record_id' => $record['id'] ?? null,
            'record_name' => $record['name'] ?? $domain->hostname,
            'record_type' => $record['type'] ?? 'CNAME',
            'record_target' => $record['content'] ?? $target,
            'proxied' => (bool) ($record['proxied'] ?? $this->proxyCustomDomains()),
            'expected_target' => $target,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTenantDomain(TenantDomain $domain, string $target): array
    {
        $hostname = $this->normalizeHostname($domain->hostname);
        $expectedTarget = $this->normalizeHostname($target);
        $records = $this->lookupCnameRecords($hostname);

        $matchedRecord = collect($records)->first(function (array $record) use ($expectedTarget): bool {
            return $this->normalizeHostname((string) ($record['target'] ?? '')) === $expectedTarget;
        });

        $observedTargets = array_values(array_filter(array_map(function (array $record): ?string {
            $target = $this->normalizeHostname((string) ($record['target'] ?? ''));

            return $target !== '' ? $target : null;
        }, $records)));

        if ($matchedRecord !== null) {
            return [
                'verified' => true,
                'check_type' => 'dns',
                'message' => 'Custom domain DNS is pointing to the storefront target.',
                'payload_json' => [
                    'hostname' => $hostname,
                    'expected_target' => $expectedTarget,
                    'observed_targets' => $observedTargets,
                ],
            ];
        }

        $cloudflareRecord = $this->findMatchingCloudflareRecord($domain, $expectedTarget);

        if ($cloudflareRecord !== null) {
            return [
                'verified' => true,
                'check_type' => 'cloudflare_api',
                'message' => 'Cloudflare DNS record matches the storefront target.',
                'payload_json' => [
                    'hostname' => $hostname,
                    'expected_target' => $expectedTarget,
                    'observed_targets' => $observedTargets,
                    'cloudflare_record' => [
                        'id' => $cloudflareRecord['id'] ?? null,
                        'name' => $cloudflareRecord['name'] ?? $domain->hostname,
                        'type' => $cloudflareRecord['type'] ?? 'CNAME',
                        'target' => $cloudflareRecord['content'] ?? null,
                        'proxied' => (bool) ($cloudflareRecord['proxied'] ?? false),
                    ],
                ],
            ];
        }

        return [
            'verified' => false,
            'check_type' => 'dns',
            'message' => 'DNS propagation is not complete yet. Add the exact CNAME target, wait for propagation, then try Check DNS again.',
            'payload_json' => [
                'hostname' => $hostname,
                'expected_target' => $expectedTarget,
                'observed_targets' => $observedTargets,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findMatchingCloudflareRecord(TenantDomain $domain, string $expectedTarget): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $zoneId = $this->resolveZoneId($domain);

        if ($zoneId === null) {
            return null;
        }

        return collect($this->listDnsRecords($zoneId, $domain->hostname))->first(function (array $record) use ($domain, $expectedTarget): bool {
            return $this->normalizeHostname((string) ($record['name'] ?? '')) === $this->normalizeHostname($domain->hostname)
                && strtoupper((string) ($record['type'] ?? '')) === 'CNAME'
                && $this->normalizeHostname((string) ($record['content'] ?? '')) === $expectedTarget;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findZoneForHostname(string $hostname): ?array
    {
        foreach ($this->candidateZones($hostname) as $candidate) {
            $response = $this->request('get', '/zones', [
                'name' => $candidate,
                'status' => 'active',
                'per_page' => 1,
            ]);

            $zone = Arr::first($response['result'] ?? []);

            if ($zone !== null) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function candidateZones(string $hostname): array
    {
        $labels = array_values(array_filter(explode('.', $this->normalizeHostname($hostname))));
        $candidates = [];

        for ($index = 0; $index <= max(count($labels) - 2, 0); $index++) {
            $candidates[] = implode('.', array_slice($labels, $index));
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listDnsRecords(string $zoneId, string $hostname): array
    {
        $response = $this->request('get', "/zones/{$zoneId}/dns_records", [
            'name' => $hostname,
            'per_page' => 100,
        ]);

        return array_values($response['result'] ?? []);
    }

    private function resolveZoneId(TenantDomain $domain): ?string
    {
        $configuredZoneId = trim((string) ($domain->cloudflare_zone_id ?? ''));

        if ($configuredZoneId !== '') {
            return $configuredZoneId;
        }

        $zone = $this->findZoneForHostname($domain->hostname);

        return $zone !== null ? (string) ($zone['id'] ?? '') : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $payload = []): array
    {
        $response = match (strtolower($method)) {
            'get' => $this->client()->get($path, $payload),
            'post' => $this->client()->post($path, $payload),
            'put' => $this->client()->put($path, $payload),
            default => throw ValidationException::withMessages([
                'cloudflare' => 'Unsupported Cloudflare API request.',
            ]),
        };

        $json = $response->json();

        if ($response->failed() || ($json['success'] ?? true) !== true) {
            $message = collect($json['errors'] ?? [])
                ->map(fn (array $error): ?string => $error['message'] ?? null)
                ->filter()
                ->implode(' ');

            throw ValidationException::withMessages([
                'cloudflare' => $message !== '' ? $message : 'Cloudflare request failed. Please try again.',
            ]);
        }

        return is_array($json) ? $json : [];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('cloudflare.api_base_url'))
            ->acceptJson()
            ->withToken((string) config('cloudflare.api_token'))
            ->timeout((int) config('cloudflare.timeout', 15));
    }

    private function proxyCustomDomains(): bool
    {
        return (bool) config('cloudflare.proxy_custom_domains', false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookupCnameRecords(string $hostname): array
    {
        return dns_get_record($hostname, DNS_CNAME) ?: [];
    }

    private function normalizeHostname(string $value): string
    {
        return trim(strtolower(rtrim($value, '.')));
    }
}
