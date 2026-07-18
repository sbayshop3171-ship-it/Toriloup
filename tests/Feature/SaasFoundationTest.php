<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
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

    public function test_merchant_register_provisions_active_storefront_and_membership(): void
    {
        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Merchant Owner',
                'store_name' => 'Demo Store',
                'email' => 'merchant@example.com',
                'password' => 'password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('tenant.slug', 'demo-store')
            ->assertJsonPath('domain.hostname', 'demo-store.company.com');

        $this->assertDatabaseHas('tenants', [
            'slug' => 'demo-store',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_domains', [
            'hostname' => 'demo-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('tenant_members', [
            'status' => 'active',
        ]);
    }

    public function test_merchant_register_generates_unique_store_slug_from_store_name(): void
    {
        $firstResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'First Merchant Owner',
                'store_name' => 'Demo Store',
                'email' => 'first-merchant@example.com',
                'password' => 'password',
            ]);

        $secondResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Second Merchant Owner',
                'store_name' => 'Demo Store',
                'email' => 'second-merchant@example.com',
                'password' => 'password',
            ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('tenant.slug', 'demo-store')
            ->assertJsonPath('domain.hostname', 'demo-store.company.com');

        $secondResponse
            ->assertCreated()
            ->assertJsonPath('tenant.slug', 'demo-store-2')
            ->assertJsonPath('domain.hostname', 'demo-store-2.company.com');
    }

    public function test_merchant_register_ignores_supplied_slug_and_uses_store_name_for_storefront(): void
    {
        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Slug Bypass Merchant',
                'store_name' => 'Rasel Fashion',
                'store_slug' => 'wrong-manual-slug',
                'email' => 'slug-bypass@example.com',
                'password' => 'password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('tenant.slug', 'rasel-fashion')
            ->assertJsonPath('domain.hostname', 'rasel-fashion.company.com');
    }

    public function test_merchant_register_blocks_reserved_platform_store_names(): void
    {
        foreach (['admin', 'owner', 'merchant', 'storefront'] as $reservedStoreName) {
            $response = $this
                ->withHeader('x-api-key', 'testing-key')
                ->withHeader('x-localization', 'en')
                ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                    'owner_name' => 'Reserved Name Merchant',
                    'store_name' => $reservedStoreName,
                    'email' => "{$reservedStoreName}-reserved@example.com",
                    'password' => 'password',
                ]);

            $response
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['store_name']);

            $this->assertDatabaseMissing('tenants', [
                'slug' => $reservedStoreName,
            ]);

            $this->assertDatabaseMissing('tenant_domains', [
                'hostname' => "{$reservedStoreName}.company.com",
            ]);
        }
    }

    public function test_storefront_bootstrap_returns_tenant_context_for_store_host(): void
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Bootstrap Store',
            'slug' => 'bootstrap-store',
            'store_code' => 'BOOT01',
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'bootstrap-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://bootstrap-store.company.com/api/storefront/bootstrap');

        $response
            ->assertOk()
            ->assertJsonPath('surface', 'storefront')
            ->assertJsonPath('tenant.slug', 'bootstrap-store')
            ->assertJsonPath('domain.hostname', 'bootstrap-store.company.com')
            ->assertJsonPath('data.theme_logo', null)
            ->assertJsonPath('data.theme_footer_logo', null)
            ->assertJsonStructure([
                'status',
                'surface',
                'tenant',
                'domain',
                'features',
                'payment_methods',
                'data' => ['company_name', 'site_default_currency_symbol'],
            ]);

        $settingsResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://bootstrap-store.company.com/api/frontend/setting');

        $settingsResponse
            ->assertOk()
            ->assertJsonPath('data.theme_logo', null)
            ->assertJsonPath('data.theme_footer_logo', null);
    }

    public function test_merchant_login_returns_tenant_memberships(): void
    {
        $role = new Role();
        $role->id = LegacyRole::MANAGER;
        $role->name = 'manager';
        $role->guard_name = 'web';
        $role->save();

        $user = User::factory()->create([
            'name' => 'Merchant Owner',
            'email' => 'merchant@login.test',
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => 'merchant-login',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        $tenant = Tenant::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Merchant Login Store',
            'slug' => 'merchant-login-store',
            'store_code' => 'MLGN01',
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'merchant-login-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $platformRoleId = \App\Models\PlatformRole::query()->create([
            'code' => 'merchant_owner',
            'name' => 'Merchant Owner',
            'scope' => 'merchant',
            'is_system' => true,
        ])->id;

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRoleId,
            'status' => 'active',
        ]);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/login', [
                'email' => 'merchant@login.test',
                'password' => 'password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('current_tenant.tenant.slug', 'merchant-login-store')
            ->assertJsonCount(1, 'tenants');

        $permissions = collect($response->json('permission'))->keyBy('name');
        $this->assertTrue((bool) $permissions->get('products')['access']);
        $this->assertTrue((bool) $permissions->get('purchase')['access']);
        $this->assertTrue((bool) $permissions->get('settings')['access']);

        $this
            ->withToken((string) $response->json('token'))
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://merchant.company.com/api/merchant/auth/me')
            ->assertOk()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('current_tenant.tenant.slug', 'merchant-login-store')
            ->assertJsonFragment([
                'name' => 'products',
                'access' => true,
            ]);
    }

    public function test_storefront_customer_login_returns_tenant_context(): void
    {
        $role = new Role();
        $role->id = LegacyRole::CUSTOMER;
        $role->name = 'customer';
        $role->guard_name = 'web';
        $role->save();

        $tenant = Tenant::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Customer Login Store',
            'slug' => 'customer-login-store',
            'store_code' => 'CLGN01',
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'customer-login-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $user = User::factory()->create([
            'name' => 'Customer User',
            'email' => 'customer@login.test',
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => 'customer-login',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://customer-login-store.company.com/api/storefront/auth/login', [
                'email' => 'customer@login.test',
                'password' => 'password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'storefront')
            ->assertJsonPath('tenant.slug', 'customer-login-store')
            ->assertJsonPath('domain.hostname', 'customer-login-store.company.com');
    }

    public function test_merchant_context_uses_authenticated_membership_tenant(): void
    {
        $role = new Role();
        $role->id = LegacyRole::MANAGER;
        $role->name = 'manager';
        $role->guard_name = 'web';
        $role->save();

        $user = User::factory()->create([
            'status' => 5,
            'username' => 'merchant-user',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        $tenant = Tenant::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Member Store',
            'slug' => 'member-store',
            'store_code' => 'MEMB01',
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'member-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $platformRoleId = \App\Models\PlatformRole::query()->create([
            'code' => 'merchant_owner',
            'name' => 'Merchant Owner',
            'scope' => 'merchant',
            'is_system' => true,
        ])->id;

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRoleId,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user, ['surface:merchant']);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://merchant.company.com/api/merchant/context');

        $response
            ->assertOk()
            ->assertJsonPath('tenant.slug', 'member-store')
            ->assertJsonPath('domain.hostname', 'member-store.company.com');
    }
}
