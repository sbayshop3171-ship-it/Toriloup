<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Enums\ShippingMethod;
use App\Models\PlatformRole;
use App\Models\PlatformPlan;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Models\TenantSetting;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantLaunchConfigurationTest extends TestCase
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

    public function test_merchant_can_manage_company_shipping_and_payment_methods(): void
    {
        Storage::fake('public');

        $context = $this->createMerchantContext('launch-config-store');
        $otherTenant = $this->createTenant('outside-store');
        $merchantToken = $this->merchantToken($context['user']);

        $tenantMethod = TenantPaymentMethod::query()->create([
            'tenant_id' => $context['tenant']->id,
            'provider_code' => 'cod',
            'display_name' => 'Cash on Delivery',
            'status' => true,
            'checkout_label' => 'Pay when delivered',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        $otherTenantMethod = TenantPaymentMethod::query()->create([
            'tenant_id' => $otherTenant->id,
            'provider_code' => 'stripe',
            'display_name' => 'Stripe',
            'status' => true,
            'checkout_label' => 'Card payment',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        $companyResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->post('http://merchant.company.com/api/merchant/settings/company', [
                'company_name' => 'Launch Config Store',
                'company_email' => 'owner@launch-config-store.test',
                'company_calling_code' => '+1',
                'company_phone' => '5551234567',
                'company_website' => 'merchant-input-should-not-save',
                'company_city' => 'Los Angeles',
                'company_state' => 'CA',
                'company_country_code' => 'US',
                'company_zip_code' => '90001',
                'company_latitude' => '34.0522',
                'company_longitude' => '-118.2437',
                'company_address' => '123 Launch Street',
                'company_logo_file' => UploadedFile::fake()->image('store-logo.png'),
            ]);

        $companyResponse
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Launch Config Store')
            ->assertJsonPath('data.company_email', 'owner@launch-config-store.test')
            ->assertJsonPath('data.company_website', 'https://launch-config-store.company.com')
            ->assertJsonPath('data.company_country_code', 'US');

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $context['tenant']->id,
            'setting_key' => 'company_website',
            'setting_value' => 'https://launch-config-store.company.com',
        ]);

        TenantDomain::query()
            ->where('tenant_id', $context['tenant']->id)
            ->update(['is_primary' => false]);

        TenantDomain::query()->create([
            'tenant_id' => $context['tenant']->id,
            'hostname' => 'store.launch-config-store.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'is_fallback' => false,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
            'last_checked_at' => now(),
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/settings/company')
            ->assertOk()
            ->assertJsonPath('data.company_website', 'https://store.launch-config-store.test');

        $logoSetting = TenantSetting::query()
            ->where('tenant_id', $context['tenant']->id)
            ->where('setting_key', 'company_logo')
            ->first();

        $this->assertNotNull($logoSetting);
        Storage::disk('public')->assertExists((string) $logoSetting->setting_value);

        $this->assertDatabaseHas('tenants', [
            'id' => $context['tenant']->id,
            'name' => 'Launch Config Store',
            'contact_email' => 'owner@launch-config-store.test',
            'contact_phone' => '5551234567',
            'country_code' => 'US',
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->putJson('http://merchant.company.com/api/merchant/settings/shipping', [
                'shipping_setup_method' => ShippingMethod::AREA_WISE,
                'shipping_setup_flat_rate_wise_cost' => 0,
                'shipping_setup_area_wise_default_cost' => 12.5,
            ])
            ->assertOk()
            ->assertJsonPath('data.shipping_setup_method', ShippingMethod::AREA_WISE)
            ->assertJsonPath('data.shipping_setup_area_wise_default_cost', 12.5);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $context['tenant']->id,
            'setting_key' => 'shipping_setup_area_wise_default_cost',
            'setting_value' => '12.5',
        ]);

        $paymentMethodsResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->putJson('http://merchant.company.com/api/merchant/settings/payment-methods', [
                'methods' => [
                    [
                        'id' => $tenantMethod->id,
                        'status' => false,
                        'display_name' => 'COD',
                        'checkout_label' => 'Cash on handoff',
                        'sort_order' => 3,
                    ],
                ],
            ]);

        $paymentMethodsResponse
            ->assertOk()
            ->assertJsonPath('data.0.id', $tenantMethod->id)
            ->assertJsonPath('data.0.status', false)
            ->assertJsonPath('data.0.display_name', 'COD')
            ->assertJsonPath('data.0.checkout_label', 'Cash on handoff')
            ->assertJsonPath('data.0.sort_order', 3);

        $this->assertDatabaseHas('tenant_payment_methods', [
            'id' => $tenantMethod->id,
            'tenant_id' => $context['tenant']->id,
            'status' => 0,
            'display_name' => 'COD',
            'checkout_label' => 'Cash on handoff',
            'sort_order' => 3,
        ]);

        $this->assertDatabaseHas('tenant_payment_methods', [
            'id' => $otherTenantMethod->id,
            'tenant_id' => $otherTenant->id,
            'status' => 1,
            'display_name' => 'Stripe',
            'checkout_label' => 'Card payment',
        ]);
    }

    public function test_merchant_catalog_update_routes_accept_existing_names_for_current_records(): void
    {
        $context = $this->createMerchantContext('catalog-update-store');
        $merchantToken = $this->merchantToken($context['user']);

        $category = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Ready Category',
            'slug' => 'ready-category',
            'status' => 1,
        ]);

        $brand = ProductBrand::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Ready Brand',
            'slug' => 'ready-brand',
            'status' => 1,
        ]);

        $unit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $headers = [
            'x-api-key' => 'testing-key',
            'x-localization' => 'en',
            'X-Tenant-Slug' => $context['tenant']->slug,
        ];

        $this
            ->withToken($merchantToken)
            ->withHeaders($headers)
            ->putJson("http://merchant.company.com/api/merchant/catalog/categories/{$category->id}", [
                'name' => 'Ready Category',
                'parent_id' => 'NULL',
                'description' => 'Stable category',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'Ready Category');

        $this
            ->withToken($merchantToken)
            ->withHeaders($headers)
            ->putJson("http://merchant.company.com/api/merchant/catalog/brands/{$brand->id}", [
                'name' => 'Ready Brand',
                'description' => 'Stable brand',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $brand->id)
            ->assertJsonPath('data.name', 'Ready Brand');

        $this
            ->withToken($merchantToken)
            ->withHeaders($headers)
            ->putJson("http://merchant.company.com/api/merchant/catalog/units/{$unit->id}", [
                'name' => 'Piece',
                'code' => 'pc',
                'status' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $unit->id)
            ->assertJsonPath('data.name', 'Piece')
            ->assertJsonPath('data.code', 'pc');
    }

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
        $starterPlan = PlatformPlan::query()->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'trial_days' => 0,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
            ]
        );

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRole->id,
            'status' => 'active',
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $starterPlan->id,
            'plan_code_snapshot' => 'starter',
            'plan_name_snapshot' => 'Starter',
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency_code' => 'USD',
            'price_amount' => 0,
            'starts_at' => now(),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();

        TenantSubscriptionInvoice::query()->create([
            'tenant_subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'invoice_no' => 'INV-'.Str::upper(Str::random(10)),
            'status' => 'paid',
            'currency_code' => 'USD',
            'subtotal_amount' => 0,
            'transaction_fee_amount' => 0,
            'total_amount' => 0,
            'issued_at' => now(),
            'due_at' => now(),
            'paid_at' => now(),
            'period_starts_at' => now(),
            'period_ends_at' => now()->addMonth(),
        ]);

        return compact('user', 'tenant');
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
