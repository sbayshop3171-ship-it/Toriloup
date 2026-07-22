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
        return filled(config('cloudflare.api_token')) && filled(config('cloudflare.saas_zone_id'));
    }

    /**
     * Provision or refresh a Cloudflare for SaaS custom hostname.
     *
     * @return array<string, mixed>
     */
    public function connectTenantDomain(TenantDomain $domain, string $target): array
    {
        $zoneId = $this->saasZoneId();
        $hostname = $this->normalizeHostname($domain->hostname);
        $origin = $this->normalizeHostname($target);
        $payload = $this->customHostnamePayload($hostname, $origin);
        $existing = $this->findCustomHostname($zoneId, $hostname);

        if ($existing === null) {
            $customHostname = $this->request('post', "/zones/{$zoneId}/custom_hostnames", $payload)['result'] ?? [];
        } else {
            $hostnameId = (string) ($existing['id'] ?? '');

            if ($hostnameId === '') {
                throw ValidationException::withMessages([
                    'cloudflare' => 'Cloudflare custom hostname exists, but the hostname id is missing.',
                ]);
            }

            $existingOrigin = $this->normalizeHostname((string) data_get($existing, 'custom_origin_server', ''));
            $needsUpdate = $existingOrigin !== $origin;

            if ($needsUpdate) {
                $customHostname = $this->request('patch', "/zones/{$zoneId}/custom_hostnames/{$hostnameId}", $payload)['result'] ?? [];
            } else {
                $customHostname = $existing;
            }
        }

        return $this->normalizeCustomHostnameResult($zoneId, $customHostname, $origin, $hostname);
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
                'message' => 'Custom domain DNS is pointing to the Toriloup target.',
                'payload_json' => [
                    'hostname' => $hostname,
                    'expected_target' => $expectedTarget,
                    'observed_targets' => $observedTargets,
                ],
            ];
        }

        $customHostname = $this->findCustomHostname($this->saasZoneId(), $hostname);

        if ($customHostname !== null && $this->isCustomHostnameReady($customHostname)) {
            return [
                'verified' => true,
                'check_type' => 'cloudflare_custom_hostname',
                'message' => 'Cloudflare custom hostname is active for this domain.',
                'payload_json' => [
                    'hostname' => $hostname,
                    'expected_target' => $expectedTarget,
                    'observed_targets' => $observedTargets,
                    'cloudflare_custom_hostname' => [
                        'id' => $customHostname['id'] ?? null,
                        'hostname' => $customHostname['hostname'] ?? $hostname,
                        'status' => $customHostname['status'] ?? null,
                        'ssl_status' => data_get($customHostname, 'ssl.status'),
                        'custom_origin_server' => data_get($customHostname, 'custom_origin_server'),
                    ],
                ],
            ];
        }

        if ($customHostname !== null) {
            return [
                'verified' => false,
                'check_type' => 'cloudflare_custom_hostname',
                'message' => 'Cloudflare custom hostname is still provisioning. Add the DNS record as a DNS-only CNAME, wait for propagation, then try Check DNS again.',
                'payload_json' => [
                    'hostname' => $hostname,
                    'expected_target' => $expectedTarget,
                    'observed_targets' => $observedTargets,
                    'cloudflare_custom_hostname' => [
                        'id' => $customHostname['id'] ?? null,
                        'hostname' => $customHostname['hostname'] ?? $hostname,
                        'status' => $customHostname['status'] ?? null,
                        'ssl_status' => data_get($customHostname, 'ssl.status'),
                        'custom_origin_server' => data_get($customHostname, 'custom_origin_server'),
                    ],
                ],
            ];
        }

        return [
            'verified' => false,
            'check_type' => 'dns',
            'message' => 'DNS propagation is not complete yet. Add the exact CNAME target, keep it DNS only, wait for propagation, then try Check DNS again.',
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
    private function findCustomHostname(string $zoneId, string $hostname): ?array
    {
        $response = $this->request('get', "/zones/{$zoneId}/custom_hostnames", [
            'hostname' => $hostname,
            'per_page' => 100,
        ]);

        return Arr::first($response['result'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCustomHostnameResult(string $zoneId, array $customHostname, string $origin, string $hostname): array
    {
        return [
            'zone_id' => $zoneId,
            'hostname_id' => $customHostname['id'] ?? null,
            'hostname' => $customHostname['hostname'] ?? $hostname,
            'status' => $customHostname['status'] ?? null,
            'ssl_status' => data_get($customHostname, 'ssl.status'),
            'custom_origin_server' => data_get($customHostname, 'custom_origin_server', $origin),
            'expected_target' => $origin,
            'dns_mode' => 'DNS only',
            'required_dns' => [
                'record_type' => 'CNAME',
                'record_name' => $hostname,
                'record_target' => $origin,
                'proxy_status' => 'DNS only',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customHostnamePayload(string $hostname, string $origin): array
    {
        return [
            'hostname' => $hostname,
            'custom_origin_server' => $origin,
            'ssl' => [
                'method' => 'http',
                'type' => 'dv',
                'settings' => [
                    'http2' => 'on',
                    'min_tls_version' => '1.2',
                    'tls_1_3' => 'on',
                ],
            ],
        ];
    }

    private function isCustomHostnameReady(array $customHostname): bool
    {
        $status = strtolower((string) ($customHostname['status'] ?? ''));
        $sslStatus = strtolower((string) data_get($customHostname, 'ssl.status', ''));

        return in_array($status, ['active', 'verified', 'deployed'], true)
            || in_array($sslStatus, ['active', 'verified'], true);
    }

    private function saasZoneId(): string
    {
        $zoneId = trim((string) config('cloudflare.saas_zone_id'));

        if ($zoneId === '') {
            throw ValidationException::withMessages([
                'cloudflare' => 'Cloudflare SaaS zone id is not configured yet.',
            ]);
        }

        return $zoneId;
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
            'patch' => $this->client()->patch($path, $payload),
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookupCnameRecords(string $hostname): array
    {
        return dns_get_record($hostname, DNS_CNAME) ?: [];
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
