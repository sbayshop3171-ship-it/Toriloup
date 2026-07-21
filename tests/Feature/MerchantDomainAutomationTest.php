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
        ]);

        app(SubscriptionManagerService::class)->ensureDefaultPlans();
    }

    public function test_merchant_can_connect_cloudflare_domain_and_auto_verify_it(): void
    {
        config([
            'cloudflare.api_base_url' => 'https://api.cloudflare.com/client/v4',
            'cloudflare.api_token' => 'cf-test-token',
            'cloudflare.proxy_custom_domains' => false,
        ]);

        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('domain-connect-store');
        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this->assignDomainAccessPlan($platformToken, $merchantContext['tenant']);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => 'zone_123',
                        'name' => 'gachwalas.com',
                        'status' => 'active',
                    ],
                ],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_123/dns_records?*' => Http::response([
                'success' => true,
                'result' => [],
            ], 200),
            'https://api.cloudflare.com/client/v4/zones/zone_123/dns_records' => Http::response([
                'success' => true,
                'result' => [
                    'id' => 'record_123',
                    'name' => 'gachwalas.com',
                    'type' => 'CNAME',
                    'content' => 'domain-connect-store.company.com',
                    'proxied' => false,
                ],
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
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('tenant_domains', [
            'id' => $domainId,
            'hostname' => 'gachwalas.com',
            'verification_status' => 'verified',
            'ssl_status' => 'active',
            'cloudflare_zone_id' => 'zone_123',
            'is_primary' => 1,
        ]);

        Http::assertSentCount(3);
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
