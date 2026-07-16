<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\Barcode;
use App\Models\PlatformPlan;
use App\Models\PlatformRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingWorkspaceTest extends TestCase
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

    public function test_merchant_registration_seeds_default_subscription(): void
    {
        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Billing Merchant',
                'store_name' => 'Billing Store',
                'store_slug' => 'billing-store',
                'email' => 'billing-merchant@example.com',
                'password' => 'password',
            ]);

        $tenantId = (int) $response->json('tenant.id');

        $response
            ->assertCreated()
            ->assertJsonPath('tenant.slug', 'billing-store');

        $this->assertDatabaseHas('platform_plans', [
            'code' => 'starter',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $tenantId,
            'plan_code_snapshot' => 'starter',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_subscription_invoices', [
            'tenant_id' => $tenantId,
            'status' => 'paid',
        ]);
    }

    public function test_owner_can_manage_plan_catalog_and_assign_subscriptions(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $tenant = $this->createTenant('plan-managed-store');

        $planResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/plans/pro-lite', [
                'name' => 'Pro Lite',
                'description' => 'Tighter plan for tests',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 29,
                'yearly_price' => 290,
                'transaction_fee_type' => 'fixed',
                'transaction_fee_value' => 3,
                'limits' => [
                    ['key' => 'products', 'value' => 25, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 0, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 5, 'is_unlimited' => false],
                ],
            ]);

        $planResponse
            ->assertOk()
            ->assertJsonPath('data.code', 'pro-lite')
            ->assertJsonPath('data.monthly_price', '29.00');

        $assignResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$tenant->id}/subscription", [
                'plan_code' => 'pro-lite',
                'billing_interval' => 'monthly',
            ]);

        $subscriptionId = (int) $assignResponse->json('data.id');
        $invoiceId = (int) $assignResponse->json('data.invoices.0.id');

        $assignResponse
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'pro-lite')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.invoices.0.status', 'open');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan_code' => 'pro-lite',
        ]);

        $markPaidResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/subscriptions/{$subscriptionId}/invoices/{$invoiceId}/mark-paid");

        $markPaidResponse
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid');

        $this->assertDatabaseHas('tenant_subscription_invoices', [
            'id' => $invoiceId,
            'status' => 'paid',
        ]);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/subscriptions?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.0.plan.code', 'pro-lite');
    }

    public function test_merchant_billing_summary_and_quota_limits_are_enforced(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $context = $this->createMerchantContext('quota-store');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/plans/micro', [
                'name' => 'Micro',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 9,
                'yearly_price' => 90,
                'transaction_fee_type' => 'none',
                'limits' => [
                    ['key' => 'products', 'value' => 1, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 0, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 2, 'is_unlimited' => false],
                ],
            ])
            ->assertOk();

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$context['tenant']->id}/subscription", [
                'plan_code' => 'micro',
                'billing_interval' => 'monthly',
            ])
            ->assertOk();

        $this->seedTenantCatalogProduct($context['tenant']);
        $merchantToken = $this->merchantToken($context['user']);

        $summaryResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/billing/summary');

        $summaryResponse
            ->assertOk()
            ->assertJsonPath('tenant.plan_code', 'micro')
            ->assertJsonPath('subscription.plan.code', 'micro')
            ->assertJsonPath('usage.products.used', 1)
            ->assertJsonPath('usage.products.remaining', 0)
            ->assertJsonPath('usage.custom_domains.limit', 0);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/domains', [
                'hostname' => 'blocked-domain.com',
            ])
            ->assertStatus(422);

        $barcode = Barcode::query()->create(['name' => 'EAN 13']);
        $category = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Quota Category',
            'slug' => 'quota-category',
            'status' => 1,
        ]);
        $unit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/products', [
                'name' => 'Blocked Product',
                'sku' => '9100002',
                'product_category_id' => $category->id,
                'barcode_id' => $barcode->id,
                'unit_id' => $unit->id,
                'buying_price' => 10,
                'selling_price' => 20,
                'status' => 5,
                'can_purchasable' => 1,
                'show_stock_out' => 1,
                'refundable' => 1,
                'maximum_purchase_quantity' => 10,
                'low_stock_quantity_warning' => 2,
            ])
            ->assertStatus(422);
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
            'email' => 'owner@billing.test',
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

    private function seedTenantCatalogProduct(Tenant $tenant): Product
    {
        $barcode = Barcode::query()->create(['name' => 'EAN 13']);
        $category = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Product Category',
            'slug' => 'existing-product-category',
            'status' => 1,
        ]);
        $unit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Existing Product',
            'slug' => 'existing-product',
            'sku' => '9100001',
            'product_category_id' => $category->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unit->id,
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
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
