<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Status;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SoftLaunchReadinessTest extends TestCase
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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    public function test_wrong_host_requests_are_blocked_for_all_workspace_surfaces(): void
    {
        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://merchant.company.com/api/platform/up')
            ->assertNotFound()
            ->assertJsonPath('message', 'Platform host mismatch.');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/merchant/up')
            ->assertNotFound()
            ->assertJsonPath('message', 'Merchant host mismatch.');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/merchant/auth/register', [
                'owner_name' => 'Wrong Host Merchant',
                'store_name' => 'Wrong Host Store',
                'email' => 'wrong-host@example.com',
                'password' => 'password',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Merchant host mismatch.');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://merchant.company.com/api/storefront/bootstrap')
            ->assertNotFound()
            ->assertJsonPath('message', 'Tenant could not be resolved for this host.');
    }

    public function test_storefront_order_access_is_scoped_by_tenant_host(): void
    {
        $alpha = $this->createTenant('alpha-soft');
        $beta = $this->createTenant('beta-soft');
        $customer = $this->createCustomerUser('soft-launch-customer@example.com');
        $token = $customer->createToken('storefront_auth_token', ['surface:storefront'])->plainTextToken;

        $alphaOrder = Order::query()->create([
            'tenant_id' => $alpha->id,
            'order_serial_no' => 'A1001',
            'user_id' => $customer->id,
            'tax' => 0,
            'discount' => 0,
            'subtotal' => 100,
            'total' => 100,
            'shipping_charge' => 0,
            'order_type' => OrderType::DELIVERY,
            'order_datetime' => now(),
            'payment_method' => 0,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => 1,
        ]);

        $betaOrder = Order::query()->create([
            'tenant_id' => $beta->id,
            'order_serial_no' => 'B1001',
            'user_id' => $customer->id,
            'tax' => 0,
            'discount' => 0,
            'subtotal' => 100,
            'total' => 100,
            'shipping_charge' => 0,
            'order_type' => OrderType::DELIVERY,
            'order_datetime' => now(),
            'payment_method' => 0,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => 1,
        ]);

        $this
            ->withToken($token)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://alpha-soft.company.com/api/frontend/order")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaOrder->id);

        $this
            ->withToken($token)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://alpha-soft.company.com/api/frontend/order/show/{$betaOrder->id}")
            ->assertNotFound();

        $this
            ->withToken($token)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://beta-soft.company.com/api/frontend/order")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $betaOrder->id);
    }

    public function test_soft_launch_billing_and_domain_flow_keeps_merchant_admin_stable(): void
    {
        $owner = $this->createPlatformOwner();
        $ownerToken = $this->platformToken($owner);

        $merchantResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Soft Launch Merchant',
                'store_name' => 'Soft Launch Store',
                'email' => 'soft-launch-merchant@example.com',
                'password' => 'password',
            ]);

        $merchantResponse
            ->assertCreated()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('tenant.slug', 'soft-launch-store');

        $merchantToken = (string) $merchantResponse->json('token');
        $tenantId = (int) $merchantResponse->json('tenant.id');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', 'soft-launch-store')
            ->getJson('http://merchant.company.com/api/merchant/billing/summary')
            ->assertOk()
            ->assertJsonPath('tenant.id', $tenantId)
            ->assertJsonPath('tenant.plan_code', 'starter');

        $this
            ->withToken($ownerToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/plans/soft-launch-domain', [
                'name' => 'Soft Launch Domain',
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
            ->withToken($ownerToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenantId}/subscription", [
                'plan_code' => 'soft-launch-domain',
                'billing_interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $domainResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', 'soft-launch-store')
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'softlaunch.example.com',
                'dns_provider' => 'cloudflare',
            ]);

        $domainId = (int) $domainResponse->json('data.id');

        $domainResponse
            ->assertCreated()
            ->assertJsonPath('data.hostname', 'softlaunch.example.com')
            ->assertJsonPath('data.verification_status', 'pending');

        $this
            ->withToken($ownerToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/domains/{$domainId}/verify", [
                'verification_status' => 'verified',
                'ssl_status' => 'active',
                'check_type' => 'dns',
                'dns_provider' => 'cloudflare',
                'payload_json' => [
                    'txt_ok' => true,
                    'cname_ok' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://softlaunch.example.com/api/storefront/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'soft-launch-store')
            ->assertJsonPath('domain.hostname', 'softlaunch.example.com');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', 'soft-launch-store')
            ->getJson('http://merchant.company.com/api/merchant/context')
            ->assertOk()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('tenant.slug', 'soft-launch-store');
    }

    public function test_soft_launch_onboarding_command_supports_dry_run_and_real_provisioning(): void
    {
        $manifestPath = storage_path('app/testing/soft-launch-manifest.json');
        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode([
            'defaults' => [
                'primary_locale' => 'en',
                'primary_currency_code' => 'USD',
                'timezone' => 'UTC',
            ],
            'merchants' => [
                [
                    'owner_name' => 'Dry Run Merchant',
                    'store_name' => 'Dry Run Store',
                    'email' => 'dry-run-store@example.com',
                    'password' => 'password',
                ],
                [
                    'owner_name' => 'Live Merchant',
                    'store_name' => 'Live Store',
                    'email' => 'live-store@example.com',
                    'password' => 'password',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this
            ->artisan('ops:soft-launch-onboard', ['manifest' => $manifestPath, '--dry-run' => true])
            ->expectsOutputToContain('would_create')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('tenants', [
            'slug' => 'dry-run-store',
        ]);

        $this
            ->artisan('ops:soft-launch-onboard', ['manifest' => $manifestPath, '--mark-live' => true])
            ->expectsOutputToContain('created')
            ->assertExitCode(0);

        $this->assertDatabaseHas('tenants', [
            'slug' => 'dry-run-store',
            'status' => 'active',
            'onboarding_status' => 'live',
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'hostname' => 'live-store.company.com',
            'domain_type' => 'subdomain',
        ]);

        $this
            ->artisan('ops:soft-launch-audit')
            ->expectsOutputToContain('tenants_live:')
            ->assertExitCode(0);
    }

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 6)).'01',
            'status' => 'active',
            'plan_code' => 'starter',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
            'contact_email' => "{$slug}@store.test",
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

    private function createCustomerUser(string $email): User
    {
        $role = Role::query()->find(LegacyRole::CUSTOMER);

        if ($role === null) {
            $role = new Role();
            $role->id = LegacyRole::CUSTOMER;
            $role->name = 'customer';
            $role->guard_name = 'web';
            $role->save();
        }

        $user = User::factory()->create([
            'name' => 'Soft Launch Customer',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => Status::ACTIVE,
            'username' => 'soft-customer-'.Str::random(4),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createPlatformOwner(): User
    {
        $role = Role::query()->find(LegacyRole::ADMIN);

        if ($role === null) {
            $role = new Role();
            $role->id = LegacyRole::ADMIN;
            $role->name = 'admin';
            $role->guard_name = 'web';
            $role->save();
        }

        $user = User::factory()->create([
            'name' => 'Soft Launch Owner',
            'email' => 'soft-launch-owner@example.com',
            'password' => bcrypt('password'),
            'status' => Status::ACTIVE,
            'username' => 'soft-launch-owner',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function platformToken(User $owner): string
    {
        return $owner->createToken('platform_auth_token', ['surface:platform'])->plainTextToken;
    }
}
