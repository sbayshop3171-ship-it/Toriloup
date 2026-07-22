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
        return $this->isSaasConfigured();
    }

    public function isSaasConfigured(): bool
    {
        return filled(config('cloudflare.api_token')) && filled(config('cloudflare.saas_zone_id'));
    }

    public function isFullZoneConfigured(): bool
    {
        return filled(config('cloudflare.api_token')) && filled(config('cloudflare.account_id'));
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
     * Create or refresh a full Cloudflare DNS zone for an apex/root customer domain.
     *
     * @return array<string, mixed>
     */
    public function connectTenantZone(TenantDomain $domain, string $target): array
    {
        $zoneName = $this->normalizeHostname($domain->hostname);
        $origin = $this->normalizeHostname($target);
        $zone = $this->findZone($zoneName);
        $created = false;

        if ($zone === null) {
            $zone = $this->createZone($zoneName);
            $created = true;
        }

        $zoneId = (string) ($zone['id'] ?? '');

        if ($zoneId === '') {
            throw ValidationException::withMessages([
                'cloudflare' => 'Cloudflare zone exists, but the zone id is missing.',
            ]);
        }

        $activationCheck = $this->triggerActivationCheckIfPending($zone);
        $dnsRecords = $this->ensureStorefrontDnsRecords($zoneId, $zoneName, $origin);

        return $this->normalizeFullZoneResult($zone, $dnsRecords, $origin, $created, $activationCheck);
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyTenantZone(TenantDomain $domain, string $target): array
    {
        $zoneName = $this->normalizeHostname($domain->hostname);
        $origin = $this->normalizeHostname($target);
        $zone = filled($domain->cloudflare_zone_id)
            ? $this->getZone((string) $domain->cloudflare_zone_id)
            : $this->findZone($zoneName);

        if ($zone === null) {
            return [
                'verified' => false,
                'check_type' => 'dns',
                'message' => 'Cloudflare DNS zone has not been created yet. Create the zone first, then update nameservers at the registrar.',
                'payload_json' => [
                    'hostname' => $zoneName,
                    'expected_target' => $origin,
                ],
            ];
        }

        $zoneId = (string) ($zone['id'] ?? '');

        if ($zoneId === '') {
            throw ValidationException::withMessages([
                'cloudflare' => 'Cloudflare zone exists, but the zone id is missing.',
            ]);
        }

        $activationCheck = $this->triggerActivationCheckIfPending($zone);
        $dnsRecords = $this->ensureStorefrontDnsRecords($zoneId, $zoneName, $origin);

        return $this->normalizeFullZoneResult($zone, $dnsRecords, $origin, false, $activationCheck);
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
    private function findZone(string $zoneName): ?array
    {
        $response = $this->request('get', '/zones', [
            'name' => $zoneName,
            'account.id' => $this->accountId(),
            'per_page' => 50,
        ]);

        return Arr::first($response['result'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getZone(string $zoneId): ?array
    {
        $response = $this->request('get', "/zones/{$zoneId}");
        $zone = $response['result'] ?? null;

        return is_array($zone) ? $zone : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function createZone(string $zoneName): array
    {
        $response = $this->request('post', '/zones', [
            'name' => $zoneName,
            'account' => [
                'id' => $this->accountId(),
            ],
            'type' => 'full',
        ]);

        return $response['result'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @return array<string, mixed>
     */
    private function triggerActivationCheckIfPending(array $zone): array
    {
        $zoneId = (string) ($zone['id'] ?? '');
        $status = strtolower((string) ($zone['status'] ?? ''));

        if ($zoneId === '' || $status !== 'pending') {
            return [
                'attempted' => false,
                'message' => null,
            ];
        }

        try {
            $this->request('put', "/zones/{$zoneId}/activation_check");

            return [
                'attempted' => true,
                'message' => 'Cloudflare activation check was triggered.',
            ];
        } catch (ValidationException $exception) {
            return [
                'attempted' => true,
                'message' => $this->firstValidationMessage($exception),
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ensureStorefrontDnsRecords(string $zoneId, string $zoneName, string $target): array
    {
        return array_map(
            fn (array $record): array => $this->upsertDnsRecord($zoneId, $record),
            $this->desiredFullZoneRecords($zoneName, $target)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function desiredFullZoneRecords(string $zoneName, string $target): array
    {
        $ttl = max((int) config('cloudflare.full_zone.ttl', 1), 1);
        $proxied = (bool) config('cloudflare.full_zone.proxy_records', true);
        $ipv4 = trim((string) config('cloudflare.full_zone.origin_ipv4', ''));
        $ipv6 = trim((string) config('cloudflare.full_zone.origin_ipv6', ''));
        $records = [];

        if ($ipv4 !== '') {
            $records[] = $this->dnsRecordPayload('A', $zoneName, $ipv4, $ttl, $proxied);
        }

        if ($ipv6 !== '') {
            $records[] = $this->dnsRecordPayload('AAAA', $zoneName, $ipv6, $ttl, $proxied);
        }

        if ($records === []) {
            $records[] = $this->dnsRecordPayload('CNAME', $zoneName, $target, $ttl, $proxied);
            $records[] = $this->dnsRecordPayload('CNAME', 'www.'.$zoneName, $target, $ttl, $proxied);

            return $records;
        }

        $records[] = $this->dnsRecordPayload('CNAME', 'www.'.$zoneName, $zoneName, $ttl, $proxied);

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function dnsRecordPayload(string $type, string $name, string $content, int $ttl, bool $proxied): array
    {
        return [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied,
        ];
    }

    /**
     * @param  array<string, mixed>  $desired
     * @return array<string, mixed>
     */
    private function upsertDnsRecord(string $zoneId, array $desired): array
    {
        $recordName = (string) $desired['name'];
        $recordType = strtoupper((string) $desired['type']);
        $existing = $this->listDnsRecords($zoneId, $recordName);

        foreach ($existing as $record) {
            $existingType = strtoupper((string) ($record['type'] ?? ''));

            if ($existingType !== $recordType && in_array($existingType, ['A', 'AAAA', 'CNAME'], true)) {
                $this->deleteDnsRecord($zoneId, (string) ($record['id'] ?? ''));
            }
        }

        $sameTypeRecord = Arr::first($existing, function (array $record) use ($recordType): bool {
            return strtoupper((string) ($record['type'] ?? '')) === $recordType;
        });

        if ($sameTypeRecord !== null && filled($sameTypeRecord['id'] ?? null)) {
            $response = $this->request(
                'put',
                "/zones/{$zoneId}/dns_records/{$sameTypeRecord['id']}",
                $desired
            );

            return $this->normalizeDnsRecordResult($response['result'] ?? $desired);
        }

        $response = $this->request('post', "/zones/{$zoneId}/dns_records", $desired);

        return $this->normalizeDnsRecordResult($response['result'] ?? $desired);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listDnsRecords(string $zoneId, string $recordName): array
    {
        $response = $this->request('get', "/zones/{$zoneId}/dns_records", [
            'name' => $recordName,
            'per_page' => 100,
        ]);

        return array_values(array_filter($response['result'] ?? [], 'is_array'));
    }

    private function deleteDnsRecord(string $zoneId, string $recordId): void
    {
        if ($recordId === '') {
            return;
        }

        $this->request('delete', "/zones/{$zoneId}/dns_records/{$recordId}");
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalizeDnsRecordResult(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'type' => $record['type'] ?? null,
            'name' => $record['name'] ?? null,
            'content' => $record['content'] ?? null,
            'proxied' => $record['proxied'] ?? null,
            'ttl' => $record['ttl'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $zone
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, mixed>  $activationCheck
     * @return array<string, mixed>
     */
    private function normalizeFullZoneResult(
        array $zone,
        array $records,
        string $origin,
        bool $created,
        array $activationCheck
    ): array {
        $zoneStatus = strtolower((string) ($zone['status'] ?? 'pending'));
        $zoneName = $this->normalizeHostname((string) ($zone['name'] ?? ''));
        $nameServers = array_values(array_filter($zone['name_servers'] ?? [], 'is_string'));

        return [
            'verified' => $zoneStatus === 'active',
            'check_type' => 'dns',
            'zone_created' => $created,
            'zone_id' => $zone['id'] ?? null,
            'zone_name' => $zoneName,
            'zone_status' => $zoneStatus,
            'name_servers' => $nameServers,
            'expected_target' => $origin,
            'dns_records' => $records,
            'activation_check' => $activationCheck,
            'required_dns' => [
                'record_type' => 'NS',
                'record_name' => $zoneName,
                'name_servers' => $nameServers,
                'registrar_action' => 'Replace the current registrar nameservers with the assigned Cloudflare nameservers.',
            ],
            'message' => $zoneStatus === 'active'
                ? 'Cloudflare nameservers are active for this domain.'
                : 'Cloudflare DNS zone is ready. Replace nameservers at the registrar, then check again.',
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

    private function accountId(): string
    {
        $accountId = trim((string) config('cloudflare.account_id'));

        if ($accountId === '') {
            throw ValidationException::withMessages([
                'cloudflare' => 'Cloudflare account id is not configured yet.',
            ]);
        }

        return $accountId;
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
            'delete' => $this->client()->delete($path, $payload),
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

    private function firstValidationMessage(ValidationException $exception): string
    {
        return (string) collect($exception->errors())->flatten()->first()
            ?: 'Cloudflare activation check could not be triggered yet.';
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
