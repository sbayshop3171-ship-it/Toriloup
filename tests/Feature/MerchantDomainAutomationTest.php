<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\Saas\CloudflareDnsService;
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantDomainAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.marketing_host' => 'company.com',
            'saas.owner_host' => 'owner.company.com',
            'saas.merchant_host' => 'merchant.company.com',
            'saas.fallback_subdomain_suffix' => 'company.com',
            'services.fastpanel.base_url' => null,
            'services.fastpanel.username' => null,
            'services.fastpanel.password' => null,
            'services.fastpanel.storefront_site_id' => null,
        ]);

        app(SubscriptionManagerService::class)->ensureDefaultPlans();
    }

    public function test_merchant_can_connect_cloudflare_domain_and_launch_it_when_storefront_probe_passes(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.saas_zone_id' => 'saas_zone_123',
            'cloudflare.proxy_custom_domains' => false,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('domain-connect-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/saas_zone_123/custom_hostnames?*' => Http::response([
                'success' => true,
                'result' => [
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/saas_zone_123/custom_hostnames' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'hostname_123',
                    'hostname' => 'gachwalas.com',
                    'status' => 'active',
                    'custom_origin_server' => 'domain-connect-store.company.com',
                    'ssl' => [
                        'status' => 'active',
                    ],
                ],
            ], 200),
            'https://gachwalas.com/api/storefront/up' => Http::response([
                'status' => true,
                'surface' => 'storefront',
                'scaffold' => 'storefront',
            ], 200),
        ]);

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'gachwalas.com',
                'dns_provider' => 'cloudflare',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.verification_status', 'pending');

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/cloudflare/connect")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.cloudflare_hostname_id', 'hostname_123')
            ->assertJsonPath('meta.verified', true);

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domainId,
            'hostname' => 'gachwalas.com',
            'verification_status' => 'verified',
            'ssl_status' => 'active',
            'cloudflare_zone_id' => 'saas_zone_123',
            'cloudflare_hostname_id' => 'hostname_123',
            'is_primary' => 1,
        ]);

        Http::assertSentCount(3);
    }

    public function test_merchant_cloudflare_connect_stays_pending_until_storefront_probe_passes(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.saas_zone_id' => 'saas_zone_456',
            'cloudflare.proxy_custom_domains' => false,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('domain-pending-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/saas_zone_456/custom_hostnames?*' => Http::response([
                'success' => true,
                'result' => [
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/saas_zone_456/custom_hostnames' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'hostname_456',
                    'hostname' => 'pending-launch.com',
                    'status' => 'pending',
                    'custom_origin_server' => 'domain-pending-store.company.com',
                    'ssl' => [
                        'status' => 'pending',
                    ],
                ],
            ], 200),
            'https://pending-launch.com/api/storefront/up' => Http::response('<html>old site</html>', 404, [
                'content-type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'pending-launch.com',
                'dns_provider' => 'cloudflare',
            ]);

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/cloudflare/connect")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.ssl_status', 'pending')
            ->assertJsonPath('data.is_primary', false)
            ->assertJsonPath('data.cloudflare_hostname_id', 'hostname_456')
            ->assertJsonPath('meta.verified', false);

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domainId,
            'hostname' => 'pending-launch.com',
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
            'cloudflare_zone_id' => 'saas_zone_456',
            'cloudflare_hostname_id' => 'hostname_456',
            'is_primary' => 0,
        ]);
    }

    public function test_merchant_can_create_cloudflare_full_zone_and_receive_nameservers(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.account_id' => 'account_123',
            'cloudflare.full_zone.origin_ipv4' => null,
            'cloudflare.full_zone.origin_ipv6' => null,
            'cloudflare.full_zone.proxy_records' => true,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('full-zone-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            $method = strtoupper($request->method());

            if ($method === 'GET' && str_contains($url, '/zones?')) {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/zones')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => 'zone_full_123',
                        'name' => 'zeroinvest.space',
                        'status' => 'pending',
                        'name_servers' => ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com'],
                    ],
                ], 200);
            }

            if ($method === 'PUT' && str_ends_with($url, '/zones/zone_full_123/activation_check')) {
                return Http::response(['success' => true, 'result' => null], 200);
            }

            if ($method === 'GET' && str_contains($url, '/zones/zone_full_123/dns_records')) {
                return Http::response(['success' => true, 'result' => []], 200);
            }

            if ($method === 'POST' && str_ends_with($url, '/zones/zone_full_123/dns_records')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => 'record_'.md5((string) $request['name']),
                        'type' => $request['type'],
                        'name' => $request['name'],
                        'content' => $request['content'],
                        'proxied' => $request['proxied'],
                        'ttl' => $request['ttl'],
                    ],
                ], 200);
            }

            return Http::response(['success' => false, 'errors' => [['message' => 'Unexpected request '.$method.' '.$url]]], 500);
        });

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'zeroinvest.space',
                'dns_provider' => 'cloudflare',
                'dns_setup_mode' => 'full_zone',
            ]);

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/cloudflare/connect")
            ->assertOk()
            ->assertJsonPath('data.dns_setup_mode', 'full_zone')
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.cloudflare_zone_id', 'zone_full_123')
            ->assertJsonPath('data.cloudflare_zone_status', 'pending')
            ->assertJsonPath('data.cloudflare_name_servers.0', 'ada.ns.cloudflare.com')
            ->assertJsonPath('meta.zone_active', false)
            ->assertJsonPath('meta.nameservers.1', 'bob.ns.cloudflare.com');

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domainId,
            'hostname' => 'zeroinvest.space',
            'dns_setup_mode' => 'full_zone',
            'cloudflare_zone_id' => 'zone_full_123',
            'cloudflare_zone_status' => 'pending',
            'verification_status' => 'pending',
            'is_primary' => 0,
        ]);

        Http::assertSent(fn ($request): bool => strtoupper($request->method()) === 'POST'
            && str_ends_with((string) $request->url(), '/zones')
            && $request['account']['id'] === 'account_123');

        Http::assertSent(fn ($request): bool => strtoupper($request->method()) === 'POST'
            && str_ends_with((string) $request->url(), '/dns_records')
            && $request['type'] === 'CNAME'
            && $request['name'] === 'zeroinvest.space'
            && $request['content'] === 'full-zone-store.company.com');
    }

    public function test_merchant_can_verify_active_full_zone_and_promote_it_to_primary(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.account_id' => 'account_456',
            'cloudflare.full_zone.origin_ipv4' => null,
            'cloudflare.full_zone.origin_ipv6' => null,
            'cloudflare.full_zone.proxy_records' => true,
            'services.fastpanel.base_url' => 'https://fastpanel.test',
            'services.fastpanel.username' => 'fastuser',
            'services.fastpanel.password' => 'panel-password',
            'services.fastpanel.storefront_site_id' => 48,
            'services.fastpanel.include_www_alias' => true,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('active-zone-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake(function ($request) {
            $url = (string) $request->url();
            $method = strtoupper($request->method());

            if ($method === 'POST' && $url === 'https://fastpanel.test/login') {
                return Http::response(['token' => 'panel-token'], 200);
            }

            if ($method === 'GET' && $url === 'https://fastpanel.test/api/sites/48') {
                return Http::response([
                    'data' => [
                        'id' => 48,
                        'domain' => 'storefront.company.com',
                        'aliases' => [
                            ['id' => 9, 'name' => '*.company.com', 'raw_name' => '*.company.com'],
                        ],
                    ],
                ], 200);
            }

            if ($method === 'PUT' && $url === 'https://fastpanel.test/api/sites/48') {
                return Http::response([
                    'data' => [
                        'id' => 48,
                        'domain' => 'storefront.company.com',
                        'aliases' => $request['aliases'],
                    ],
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/zones?')) {
                return Http::response([
                    'success' => true,
                    'result' => [[
                        'id' => 'zone_active_456',
                        'name' => 'launchstore.com',
                        'status' => 'active',
                        'name_servers' => ['sue.ns.cloudflare.com', 'tim.ns.cloudflare.com'],
                    ]],
                ], 200);
            }

            if ($method === 'GET' && str_contains($url, '/zones/zone_active_456/dns_records')) {
                $name = str_contains($url, 'www.launchstore.com') ? 'www.launchstore.com' : 'launchstore.com';

                return Http::response([
                    'success' => true,
                    'result' => [[
                        'id' => 'record_'.md5($name),
                        'type' => 'CNAME',
                        'name' => $name,
                        'content' => 'active-zone-store.company.com',
                        'proxied' => true,
                        'ttl' => 1,
                    ]],
                ], 200);
            }

            if ($method === 'PUT' && str_contains($url, '/zones/zone_active_456/dns_records/')) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => basename($url),
                        'type' => $request['type'],
                        'name' => $request['name'],
                        'content' => $request['content'],
                        'proxied' => $request['proxied'],
                        'ttl' => $request['ttl'],
                    ],
                ], 200);
            }

            if ($method === 'GET' && $url === 'https://launchstore.com/api/storefront/up') {
                return Http::response([
                    'status' => true,
                    'surface' => 'storefront',
                    'scaffold' => 'storefront',
                ], 200);
            }

            return Http::response(['success' => false, 'errors' => [['message' => 'Unexpected request '.$method.' '.$url]]], 500);
        });

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'launchstore.com',
                'dns_provider' => 'cloudflare',
                'dns_setup_mode' => 'full_zone',
            ]);

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.cloudflare_zone_status', 'active')
            ->assertJsonPath('meta.verified', true)
            ->assertJsonPath('meta.zone_active', true)
            ->assertJsonPath('meta.storefront_alias_ready', true)
            ->assertJsonPath('meta.storefront_launched', true);

        Http::assertSent(fn ($request): bool => strtoupper($request->method()) === 'PUT'
            && (string) $request->url() === 'https://fastpanel.test/api/sites/48'
            && collect($request['aliases'])->contains(fn (array $alias): bool => ($alias['name'] ?? null) === 'launchstore.com')
            && collect($request['aliases'])->contains(fn (array $alias): bool => ($alias['name'] ?? null) === 'www.launchstore.com'));
    }

    public function test_failed_custom_domain_verification_restores_fallback_as_primary(): void
    {
        $this->mock(CloudflareDnsService::class, function ($mock): void {
            $mock->shouldReceive('isFullZoneConfigured')->andReturn(true);
            $mock->shouldReceive('verifyTenantZone')
                ->once()
                ->andReturn([
                    'verified' => false,
                    'zone_id' => 'zone_lost_123',
                    'zone_status' => 'pending',
                    'name_servers' => ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com'],
                    'dns_records' => [],
                    'message' => 'Replace nameservers at the registrar, then check again.',
                ]);
        });

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('restore-fallback-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        $fallbackDomain = TenantDomain::query()
            ->where('tenant_id', $merchantContext['tenant']->id)
            ->where('is_fallback', true)
            ->firstOrFail();

        $fallbackDomain->forceFill(['is_primary' => false])->save();

        $customDomain = TenantDomain::query()->create([
            'tenant_id' => $merchantContext['tenant']->id,
            'hostname' => 'lost-custom-domain.com',
            'domain_type' => 'custom',
            'is_primary' => true,
            'is_fallback' => false,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
            'dns_provider' => 'cloudflare',
            'dns_setup_mode' => 'full_zone',
            'cloudflare_zone_id' => 'zone_lost_123',
            'cloudflare_zone_status' => 'active',
            'cloudflare_name_servers' => ['ada.ns.cloudflare.com', 'bob.ns.cloudflare.com'],
            'verified_at' => now(),
            'last_checked_at' => now(),
            'verification_token' => Str::upper(Str::random(32)),
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$customDomain->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.ssl_status', 'pending')
            ->assertJsonPath('data.is_primary', false)
            ->assertJsonPath('meta.verified', false);

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $customDomain->id,
            'is_primary' => 0,
            'verification_status' => 'pending',
            'ssl_status' => 'pending',
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $fallbackDomain->id,
            'is_primary' => 1,
            'verification_status' => 'verified',
        ]);
    }

    public function test_merchant_verify_marks_cloudflare_custom_hostname_as_verified_even_when_storefront_probe_has_not_passed(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.saas_zone_id' => 'saas_zone_verify_active',
            'cloudflare.proxy_custom_domains' => false,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('verify-active-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/saas_zone_verify_active/custom_hostnames?*' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'hostname_verify_active',
                        'hostname' => 'verify-active.com',
                        'status' => 'active',
                        'custom_origin_server' => 'verify-active-store.company.com',
                        'ssl' => [
                            'status' => 'active',
                        ],
                    ],
                ],
            ], 200),
            'https://verify-active.com/api/storefront/up' => Http::response('<html>legacy site</html>', 404, [
                'content-type' => 'text/html; charset=UTF-8',
            ]),
        ]);

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'verify-active.com',
                'dns_provider' => 'cloudflare',
            ]);

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('meta.verified', true)
            ->assertJsonPath('meta.storefront_launched', false);
    }

    public function test_merchant_can_verify_manual_dns_and_activate_the_custom_domain(): void
    {
        $this->mock(CloudflareDnsService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldReceive('verifyTenantDomain')
                ->once()
                ->andReturn([
                    'verified' => true,
                    'check_type' => 'dns',
                    'message' => 'Manual DNS verification succeeded.',
                    'payload_json' => [
                        'hostname' => 'manual-gachwalas.com',
                        'expected_target' => 'manual-verify-store.company.com',
                        'observed_targets' => ['manual-verify-store.company.com'],
                    ],
                ]);
        });

        Http::fake([
            'https://manual-gachwalas.com/api/storefront/up' => Http::response([
                'status' => true,
                'surface' => 'storefront',
                'scaffold' => 'storefront',
            ], 200),
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('manual-verify-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        $createResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'manual-gachwalas.com',
                'dns_provider' => 'cloudflare',
            ]);

        $domainId = (int) $createResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('meta.verified', true);
    }

    public function test_verified_custom_domain_replaces_existing_custom_primary_automatically(): void
    {
        $this->mock(CloudflareDnsService::class, function ($mock): void {
            $mock->shouldReceive('verifyTenantDomain')
                ->once()
                ->andReturn([
                    'verified' => true,
                    'check_type' => 'dns',
                    'message' => 'Manual DNS verification succeeded.',
                    'payload_json' => [
                        'hostname' => 'fresh-primary.com',
                        'expected_target' => 'auto-primary-store.company.com',
                        'observed_targets' => ['auto-primary-store.company.com'],
                    ],
                ]);
        });

        Http::fake([
            'https://fresh-primary.com/api/storefront/up' => Http::response([
                'status' => true,
                'surface' => 'storefront',
                'scaffold' => 'storefront',
            ], 200),
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('auto-primary-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);
        $tenant = $merchantContext['tenant'];

        $this->assignDomainAccessPlan($platformToken, $tenant);

        TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_fallback', true)
            ->update(['is_primary' => false]);

        $oldPrimary = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'old-primary.com',
            'domain_type' => 'custom',
            'is_primary' => true,
            'is_fallback' => false,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
            'dns_provider' => 'cloudflare',
            'verified_at' => now(),
            'verification_token' => Str::upper(Str::random(32)),
        ]);

        $freshDomain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'fresh-primary.com',
            'domain_type' => 'custom',
            'is_primary' => false,
            'is_fallback' => false,
            'ssl_status' => 'pending',
            'verification_status' => 'pending',
            'dns_provider' => 'cloudflare',
            'dns_setup_mode' => 'cname',
            'verification_token' => Str::upper(Str::random(32)),
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $tenant->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$freshDomain->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('meta.verified', true);

        $this->assertFalse((bool) $oldPrimary->fresh()->is_primary);
        $this->assertTrue((bool) $freshDomain->fresh()->is_primary);
    }

    public function test_verify_uses_cloudflare_api_when_public_cname_is_flattened_or_missing(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.saas_zone_id' => 'saas_zone_789',
            'cloudflare.proxy_custom_domains' => false,
        ]);

        $tenant = $this->createTenant('flattened-check-store');
        $domain = TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'flattened-check.invalid',
            'domain_type' => 'custom',
            'is_primary' => false,
            'is_fallback' => false,
            'ssl_status' => 'pending',
            'verification_status' => 'pending',
            'dns_provider' => 'cloudflare',
            'cloudflare_zone_id' => 'zone_123',
            'verification_token' => Str::upper(Str::random(32)),
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones/saas_zone_789/custom_hostnames?*' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'hostname_789',
                        'hostname' => 'flattened-check.invalid',
                        'status' => 'active',
                        'custom_origin_server' => 'flattened-check-store.company.com',
                        'ssl' => [
                            'status' => 'active',
                        ],
                    ],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_123/custom_hostnames?*' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'hostname_123',
                        'hostname' => 'flattened-check.invalid',
                        'status' => 'active',
                        'custom_origin_server' => 'flattened-check-store.company.com',
                        'ssl' => [
                            'status' => 'active',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(CloudflareDnsService::class)->verifyTenantDomain($domain, 'flattened-check-store.company.com');

        $this->assertTrue($result['verified']);
        $this->assertSame('cloudflare_custom_hostname', $result['check_type']);
        $this->assertSame('Cloudflare custom hostname is active for this domain.', $result['message']);
        $this->assertSame('flattened-check-store.company.com', data_get($result, 'payload_json.cloudflare_custom_hostname.custom_origin_server'));

        Http::assertSentCount(1);
    }

    /**
     * @return array{user: User, tenant: Tenant}
     */
    private function createMerchantContext(string $slug): array
    {
        $role = $this->seedLegacyRole(LegacyRole::MANAGER, 'manager');
        $user = User::factory()->create([
            'name' => Str::headline($slug).' Merchant',
            'email' => $slug.'@merchant.test',
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => $slug.'-merchant',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        $tenant = $this->createTenant($slug);
        $platformRole = PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_owner'],
            ['name' => 'Merchant Owner', 'scope' => 'merchant', 'is_system' => true]
        );

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRole->id,
            'status' => 'active',
        ]);

        return compact('user', 'tenant');
    }

    private function createPlatformOwner(): User
    {
        $role = $this->seedLegacyRole(LegacyRole::ADMIN, 'admin');
        $user = User::factory()->create([
            'name' => 'Platform Owner',
            'email' => 'owner@platform.test',
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => 'platform-owner',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 4)).strtoupper(Str::random(4)),
            'status' => 'active',
            'plan_code' => 'starter',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
            'contact_email' => $slug.'@store.test',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => "{$slug}.company.com",
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);

        return $tenant;
    }

    private function merchantToken(User $user): string
    {
        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertCreated()->assertJsonPath('surface', 'merchant');

        return (string) $response->json('token');
    }

    private function platformToken(User $user): string
    {
        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/platform/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertCreated()->assertJsonPath('surface', 'platform');

        return (string) $response->json('token');
    }

    private function assignDomainAccessPlan(string $platformToken, Tenant $tenant): void
    {
        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/plans/domain-access', [
                'name' => 'Domain Access',
                'status' => 'active',
                'is_public' => false,
                'currency_code' => 'USD',
                'prices' => [
                    'monthly' => 0,
                    'semiannual' => 0,
                    'yearly' => 0,
                ],
                'limits' => [
                    ['key' => 'products', 'value' => 20, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 1, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 1, 'is_unlimited' => false],
                ],
                'features' => [
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true],
                ],
            ])
            ->assertOk();

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenant->id}/subscription", [
                'plan_code' => 'domain-access',
                'billing_interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    private function seedLegacyRole(int $id, string $name): Role
    {
        $role = new Role();
        $role->id = $id;
        $role->name = $name;
        $role->guard_name = 'web';
        $role->save();

        return $role;
    }
}
