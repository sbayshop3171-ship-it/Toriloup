<?php

namespace Tests\Feature;

use App\Libraries\AppLibrary;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Models\Customer;
use App\Models\Order;
use App\Enums\Role as LegacyRole;
use App\Models\PaymentGateway;
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

    public function test_platform_owner_can_monitor_orders_without_operational_actions(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $tenantA = $this->createTenant('order-monitor-alpha');
        $tenantB = $this->createTenant('order-monitor-beta');
        $gateway = PaymentGateway::query()->create([
            'name' => 'Cash on Delivery',
            'slug' => 'cashondelivery',
            'status' => Status::ACTIVE,
        ]);

        $alphaOrder = Order::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantA->id,
            'order_serial_no' => 'MONITOR-ALPHA-1',
            'user_id' => $owner->id,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 100,
            'order_datetime' => now(),
            'payment_method' => $gateway->id,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => 1,
        ]);

        Order::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantB->id,
            'order_serial_no' => 'MONITOR-BETA-1',
            'user_id' => $owner->id,
            'subtotal' => 200,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 200,
            'order_datetime' => now()->subMinute(),
            'payment_method' => $gateway->id,
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
        ]);

        $response = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/orders?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('summary.total_orders', 2)
            ->assertJsonPath('summary.pending_orders', 1)
            ->assertJsonPath('summary.paid_orders', 1)
            ->assertJsonFragment(['slug' => 'order-monitor-alpha'])
            ->assertJsonFragment(['slug' => 'order-monitor-beta']);

        $this->assertEquals(300.0, $response->json('summary.gross_sales'));

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/orders?q=order-monitor-alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaOrder->id)
            ->assertJsonPath('data.0.tenant.slug', 'order-monitor-alpha');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/orders/{$alphaOrder->id}/status", [
                'status' => OrderStatus::CONFIRMED,
            ])
            ->assertStatus(405);
    }

    public function test_owner_can_view_merchant_directory_details_and_delete_merchant(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $merchantContext = $this->createMerchantContext('owner-directory-store');
        $tenant = $merchantContext['tenant'];

        Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Directory Product 1',
            'slug' => 'directory-product-1',
            'sku' => 'DIR-001',
            'buying_price' => 20,
            'selling_price' => 35,
            'variation_price' => 35,
            'status' => Status::ACTIVE,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Product::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Directory Product 2',
            'slug' => 'directory-product-2',
            'sku' => 'DIR-002',
            'buying_price' => 25,
            'selling_price' => 40,
            'variation_price' => 40,
            'status' => Status::ACTIVE,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Customer::query()->create([
            'tenant_id' => $tenant->id,
            'legacy_user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Directory Linked Customer',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenant->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Directory Guest Customer',
            'email' => 'directory-guest@example.com',
        ]);

        Order::query()->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'ORD-DIRECTORY-1',
            'user_id' => $owner->id,
            'subtotal' => 150,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 150,
            'order_datetime' => now(),
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
        ]);

        $listResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/tenants');

        $listResponse->assertOk();

        $listedTenant = collect($listResponse->json('data'))->firstWhere('id', $tenant->id);

        $this->assertNotNull($listedTenant);
        $this->assertSame('owner-directory-store.company.com', $listedTenant['storefront_hostname']);
        $this->assertSame(2, $listedTenant['products_count']);
        $this->assertSame(2, $listedTenant['customers_count']);
        $this->assertSame(1, $listedTenant['completed_orders_count']);
        $this->assertEquals(150.0, $listedTenant['completed_sales_total']);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://owner.company.com/api/platform/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.storefront_hostname', 'owner-directory-store.company.com')
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.active_members_count', 1)
            ->assertJsonPath('data.products_count', 2)
            ->assertJsonPath('data.customers_count', 2)
            ->assertJsonPath('data.completed_orders_count', 1)
            ->assertJsonPath('data.completed_sales_total', 150);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->deleteJson("http://owner.company.com/api/platform/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.deleted', true);

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('tenant_domains', ['tenant_id' => $tenant->id]);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://owner.company.com/api/platform/tenants/{$tenant->id}")
            ->assertNotFound();
    }

    public function test_owner_can_view_master_customer_directory_across_merchants(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $tenantA = $this->createTenant('customer-alpha');
        $tenantB = $this->createTenant('customer-beta');

        Customer::query()->create([
            'tenant_id' => $tenantA->id,
            'legacy_user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy Alpha',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'legacy_user_id' => $owner->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Legacy Beta',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantA->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Alpha',
            'email' => 'shared-directory@example.com',
        ]);

        Customer::query()->create([
            'tenant_id' => $tenantB->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Shared Beta',
            'email' => 'SHARED-DIRECTORY@example.com',
        ]);

        Order::query()->create([
            'tenant_id' => $tenantA->id,
            'order_serial_no' => 'ORD-CUSTOMER-ALPHA',
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
            'order_serial_no' => 'ORD-CUSTOMER-BETA',
            'user_id' => $owner->id,
            'subtotal' => 80,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 80,
            'order_datetime' => now()->subMinute(),
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::DELIVERED,
            'active' => 1,
        ]);

        $directoryResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/customers');

        $directoryResponse->assertOk();

        $customers = collect($directoryResponse->json('data'));

        $this->assertCount(2, $customers);

        $legacyGroup = $customers->firstWhere('legacy_user_id', $owner->id);
        $sharedEmailGroup = $customers->firstWhere('email', 'shared-directory@example.com');

        $this->assertNotNull($legacyGroup);
        $this->assertNotNull($sharedEmailGroup);

        $this->assertSame(2, $legacyGroup['linked_merchants_count']);
        $this->assertSame(2, $legacyGroup['total_orders']);
        $this->assertEquals(200.0, $legacyGroup['total_spend']);

        $this->assertSame(2, $sharedEmailGroup['linked_merchants_count']);
        $this->assertSame(2, $sharedEmailGroup['shadow_profiles_count']);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson("http://owner.company.com/api/platform/customers/{$legacyGroup['id']}")
            ->assertOk()
            ->assertJsonPath('data.legacy_user_id', $owner->id)
            ->assertJsonPath('data.linked_merchants_count', 2)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.total_spend', 200)
            ->assertJsonCount(2, 'data.linked_merchants')
            ->assertJsonFragment([
                'tenant_slug' => 'customer-alpha',
                'storefront_hostname' => 'customer-alpha.company.com',
                'orders_count' => 1,
                'spend_total' => 120.0,
            ])
            ->assertJsonFragment([
                'tenant_slug' => 'customer-beta',
                'storefront_hostname' => 'customer-beta.company.com',
                'orders_count' => 1,
                'spend_total' => 80.0,
            ]);
    }

    public function test_custom_domain_stays_storefront_only_and_requires_owner_verification(): void
    {
        $owner = $this->createPlatformOwner();
        $merchantContext = $this->createMerchantContext('domain-store');

        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($merchantContext['user']);

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
            ->postJson("http://owner.company.com/api/platform/tenants/{$merchantContext['tenant']->id}/subscription", [
                'plan_code' => 'domain-access',
                'billing_interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

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
