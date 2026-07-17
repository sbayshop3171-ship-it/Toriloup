<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformRole;
use App\Models\PlatformSupportSession;
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

    public function test_platform_overview_and_tenant_detail_include_operational_insights(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $merchantContext = $this->createMerchantContext('insight-store');
        $legacyCustomer = $this->createLegacyCustomer('insight-customer@example.com', 'insight-customer');

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $merchantContext['tenant']->id,
            'legacy_user_id' => $legacyCustomer->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Insight Customer',
            'email' => $legacyCustomer->email,
            'status' => 1,
            'last_login_at' => now(),
        ]);

        $this->createPaidOrder($merchantContext['tenant'], $legacyCustomer, 150);

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
                    'gmv_total',
                    'active_subscriptions',
                    'failed_renewals',
                    'support_sessions_active',
                ],
                'merchant_growth',
                'sales_trend',
                'top_merchants',
                'merchants_needing_action',
                'latest_audit_events',
            ])
            ->assertJsonPath('summary.customers_total', 1)
            ->assertJsonPath('summary.gmv_total', 150);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://owner.company.com/api/platform/tenants/{$merchantContext['tenant']->id}")
            ->assertOk()
            ->assertJsonPath('data.stats.products_count', 0)
            ->assertJsonPath('data.stats.customers_count', 1)
            ->assertJsonPath('data.stats.total_orders_count', 1)
            ->assertJsonPath('data.stats.completed_orders_count', 1)
            ->assertJsonPath('data.stats.gmv_total', 150)
            ->assertJsonPath('data.storefront_url', 'https://insight-store.company.com');
    }

    public function test_platform_owner_can_view_global_customer_master_directory(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $alphaTenant = $this->createTenant('alpha-store');
        $betaTenant = $this->createTenant('beta-store');
        $legacyCustomer = $this->createLegacyCustomer('master-customer@example.com', 'master-customer');

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $alphaTenant->id,
            'legacy_user_id' => $legacyCustomer->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Master Customer',
            'email' => $legacyCustomer->email,
            'phone' => '01710000001',
            'country_code' => '+880',
            'status' => 1,
        ]);

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $betaTenant->id,
            'legacy_user_id' => $legacyCustomer->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Master Customer',
            'email' => $legacyCustomer->email,
            'phone' => '01710000001',
            'country_code' => '+880',
            'status' => 1,
        ]);

        $this->createPaidOrder($alphaTenant, $legacyCustomer, 80);
        $this->createPaidOrder($betaTenant, $legacyCustomer, 120);

        $indexResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.linked_merchants_count', 2)
            ->assertJsonPath('data.0.total_orders', 2)
            ->assertJsonPath('data.0.total_spend', 200);

        $masterCustomerId = (string) $indexResponse->json('data.0.id');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://owner.company.com/api/platform/customers/{$masterCustomerId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'master-customer@example.com')
            ->assertJsonPath('data.linked_merchants_count', 2)
            ->assertJsonPath('data.total_spend', 200)
            ->assertJsonCount(2, 'data.linked_merchants');
    }

    public function test_owner_support_session_can_be_consumed_and_closed_with_audit_visibility(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $merchantContext = $this->createMerchantContext('support-store');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/platform/support/impersonations', [
                'tenant_id' => $merchantContext['tenant']->id,
                'reason' => 'Investigating onboarding issue',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.tenant.slug', $merchantContext['tenant']->slug);

        $session = PlatformSupportSession::query()->latest('id')->firstOrFail();

        $consumeResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/support-sessions/consume', [
                'handoff_code' => $session->handoff_code,
            ]);

        $consumeResponse
            ->assertCreated()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('current_tenant.slug', $merchantContext['tenant']->slug)
            ->assertJsonPath('support_session.id', $session->id)
            ->assertJsonPath('support_session.status', 'active');

        $merchantSupportToken = (string) $consumeResponse->json('token');

        $this
            ->withToken($merchantSupportToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://merchant.company.com/api/merchant/auth/me')
            ->assertOk()
            ->assertJsonPath('support_session.id', $session->id)
            ->assertJsonPath('support_session.status', 'active')
            ->assertJsonPath('current_tenant.slug', $merchantContext['tenant']->slug);

        $this
            ->withToken($merchantSupportToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://merchant.company.com/api/merchant/auth/support-sessions/{$session->id}/end")
            ->assertOk()
            ->assertJsonPath('data.status', 'ended');

        $this->assertDatabaseHas('platform_support_sessions', [
            'id' => $session->id,
            'status' => 'ended',
        ]);
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

    private function createLegacyCustomer(string $email, string $username): User
    {
        $role = Role::query()->find(LegacyRole::CUSTOMER);

        if ($role === null) {
            $role = $this->seedLegacyRole(LegacyRole::CUSTOMER, 'customer');
        }

        $user = User::factory()->create([
            'name' => Str::headline($username),
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => $username,
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createPaidOrder(Tenant $tenant, User $customer, float $total): Order
    {
        return Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'order_serial_no' => 'ORD-'.Str::upper(Str::random(8)),
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => $total,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
            'order_datetime' => now(),
        ]);
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
