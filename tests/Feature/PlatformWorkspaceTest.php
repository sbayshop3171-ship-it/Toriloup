<?php

namespace Tests\Feature;

use App\Libraries\AppLibrary;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Models\Customer;
use App\Models\Order;
use App\Enums\Role as LegacyRole;
use App\Models\PlatformRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformWorkspaceTest extends TestCase
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
    }

    public function test_platform_owner_can_manage_tenants_and_providers(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $tenant = $this->createTenant('managed-store', 'draft');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/overview')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'summary' => [
                    'merchants_total',
                    'products_total',
                    'customers_total',
                    'revenue_total',
                    'revenue_total_display',
                    'tenants_total',
                    'tenants_active',
                    'tenants_draft',
                    'tenants_suspended',
                    'tenants_live',
                    'tenants_onboarding',
                    'new_signups_today',
                    'custom_domains_pending',
                    'custom_domains_verified',
                    'domain_issues',
                    'provider_issues',
                    'merchant_memberships_active',
                    'subscriptions_active',
                    'orders_today',
                    'gmv_today',
                    'support_alerts',
                ],
            ]);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->patchJson("http://owner.company.com/api/platform/tenants/{$tenant->id}", [
                'plan_code' => 'growth',
                'contact_email' => 'owner@managed-store.test',
                'onboarding_status' => 'catalog_started',
            ])
            ->assertOk()
            ->assertJsonPath('data.plan_code', 'growth')
            ->assertJsonPath('data.contact_email', 'owner@managed-store.test')
            ->assertJsonPath('data.onboarding_status', 'catalog_started');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenant->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenant->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/providers/stripe', [
                'provider_type' => 'payment',
                'name' => 'Stripe',
                'status' => true,
                'config_json' => [
                    'managed_by' => 'owner',
                    'publishable_key' => 'pk_test_platform',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.provider_code', 'stripe')
            ->assertJsonPath('data.provider_type', 'payment');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/providers?provider_type=payment')
            ->assertOk()
            ->assertJsonFragment([
                'provider_code' => 'stripe',
                'name' => 'Stripe',
            ]);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/audit-logs')
            ->assertOk()
            ->assertJsonFragment([
                'action_code' => 'platform.provider.upserted',
            ]);
    }

    public function test_surface_tokens_are_isolated_between_platform_and_merchant(): void
    {
        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('secure-store');

        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/overview')
            ->assertOk();

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/overview')
            ->assertForbidden();

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/context')
            ->assertForbidden();
    }

    public function test_platform_overview_returns_owner_dashboard_totals(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $tenantA = $this->createTenant('alpha-metrics-store');
        $tenantB = $this->createTenant('beta-metrics-store');

        Product::query()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Alpha Product',
            'slug' => 'alpha-product',
            'sku' => 'ALPHA-001',
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => Status::ACTIVE,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Product::query()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Beta Product',
            'slug' => 'beta-product',
            'sku' => 'BETA-001',
            'buying_price' => 15,
            'selling_price' => 25,
            'variation_price' => 25,
            'status' => Status::ACTIVE,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantA->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Email A',
            'email' => 'shared@example.com',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Email B',
            'email' => 'SHARED@example.com',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantA->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Phone A',
            'phone' => '01700000000',
            'country_code' => '+880',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Phone B',
            'phone' => '01700000000',
            'country_code' => '+880',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantA->id,
            'legacy_user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy Linked A',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'legacy_user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy Linked B',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Anonymous Customer',
        ]);

        Order::query()->create([
            'tenant_id' => $tenantA->id,
            'order_serial_no' => 'ORD-ALPHA-1',
            'user_id' => $owner->id,
            'subtotal' => 120,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 120,
            'order_datetime' => now(),
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
        ]);

        Order::query()->create([
            'tenant_id' => $tenantB->id,
            'order_serial_no' => 'ORD-BETA-1',
            'user_id' => $owner->id,
            'subtotal' => 80,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 80,
            'order_datetime' => now(),
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
        ]);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/overview')
            ->assertOk()
            ->assertJsonPath('summary.merchants_total', 2)
            ->assertJsonPath('summary.products_total', 2)
            ->assertJsonPath('summary.customers_total', 4)
            ->assertJsonPath('summary.revenue_total', 200)
            ->assertJsonPath('summary.revenue_total_display', AppLibrary::currencyAmountFormat(200));
    }

    public function test_custom_domain_stays_storefront_only_and_requires_owner_verification(): void
    {
        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('domain-store');

        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

        $domainResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'mystore.com',
                'dns_provider' => 'cloudflare',
            ]);

        $domainResponse
            ->assertCreated()
            ->assertJsonPath('data.hostname', 'mystore.com')
            ->assertJsonPath('data.domain_type', 'custom')
            ->assertJsonPath('data.verification_status', 'pending');

        $domainId = $domainResponse->json('data.id');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://mystore.com/api/storefront/bootstrap')
            ->assertNotFound();

        $this
            ->withToken($platformToken)
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
            ->assertJsonPath('data.verification_status', 'verified')
            ->assertJsonPath('data.ssl_status', 'active');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://mystore.com/api/storefront/bootstrap')
            ->assertOk()
            ->assertJsonPath('surface', 'storefront')
            ->assertJsonPath('domain.hostname', 'mystore.com')
            ->assertJsonPath('tenant.slug', 'domain-store');

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->postJson("http://merchant.company.com/api/merchant/domains/{$domainId}/primary")
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $merchantContext['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/context')
            ->assertOk()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('tenant.slug', 'domain-store');
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

    private function createTenant(string $slug, string $status = 'active'): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 4)).strtoupper(Str::random(4)),
            'status' => $status,
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
