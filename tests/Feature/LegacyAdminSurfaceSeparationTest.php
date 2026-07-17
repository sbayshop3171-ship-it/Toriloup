<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\Barcode;
use App\Models\Customer;
use App\Models\PlatformRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegacyAdminSurfaceSeparationTest extends TestCase
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

    public function test_legacy_admin_workspace_routes_are_available_only_on_owner_host(): void
    {
        $owner = $this->createLegacyAdminUser(LegacyRole::ADMIN, 'admin', 'owner-legacy@test.com');

        Sanctum::actingAs($owner, ['surface:platform']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->getJson('http://owner.company.com/api/admin/timezone')
            ->assertOk();

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->getJson('http://merchant.company.com/api/admin/timezone')
            ->assertNotFound();
    }

    public function test_platform_owner_can_use_platform_routes_and_legacy_admin_workspace_routes(): void
    {
        $owner = $this->createLegacyAdminUser(LegacyRole::ADMIN, 'admin', 'owner-legacy@test.com');

        Sanctum::actingAs($owner, ['surface:platform']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/overview')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->getJson('http://owner.company.com/api/admin/timezone')
            ->assertOk();
    }

    public function test_merchant_can_use_allowlisted_legacy_admin_product_api_with_tenant_scope(): void
    {
        $context = $this->createMerchantContext('legacy-product-store');
        $otherTenant = $this->createTenant('outside-product-store');

        $categoryA = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Legacy Alpha',
            'slug' => 'legacy-alpha',
            'status' => 1,
        ]);
        $unitA = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);
        $categoryB = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Legacy Beta',
            'slug' => 'legacy-beta',
            'status' => 1,
        ]);
        $unitB = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Box',
            'code' => 'bx',
            'status' => 1,
        ]);
        $barcode = Barcode::query()->create(['name' => 'EAN 13']);

        $productA = Product::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Merchant Legacy Product',
            'slug' => 'merchant-legacy-product',
            'sku' => 'LEGACY-A',
            'product_category_id' => $categoryA->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitA->id,
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

        $productB = Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Outside Legacy Product',
            'slug' => 'outside-legacy-product',
            'sku' => 'LEGACY-B',
            'product_category_id' => $categoryB->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitB->id,
            'buying_price' => 11,
            'selling_price' => 22,
            'variation_price' => 22,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson('http://merchant.company.com/api/admin/product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productA->id);

        $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson("http://merchant.company.com/api/admin/product/show/{$productB->id}")
            ->assertNotFound();
    }

    public function test_merchant_cannot_use_owner_only_legacy_admin_routes(): void
    {
        $context = $this->createMerchantContext('legacy-owner-only-store');

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson('http://merchant.company.com/api/admin/setting/site')
            ->assertNotFound();
    }

    public function test_merchant_legacy_user_modules_are_tenant_scoped(): void
    {
        $context = $this->createMerchantContext('legacy-users-store', [
            'administrators',
            'administrators_show',
            'customers',
            'customers_show',
            'employees',
            'employees_show',
        ]);
        $otherTenant = $this->createTenant('outside-users-store');

        $managerRole = $this->createLegacyRoleWithPermissions(LegacyRole::MANAGER, 'manager', []);
        $customerRole = $this->createLegacyRoleWithPermissions(LegacyRole::CUSTOMER, 'customer', []);
        $employeeRole = $this->createLegacyRoleWithPermissions(LegacyRole::POS_OPERATOR, 'pos operator', []);

        $storeAdmin = $this->createTenantUser('Store Scoped Admin', 'store-admin@tenant.test', $managerRole, $context['tenant']);
        $outsideAdmin = $this->createTenantUser('Outside Scoped Admin', 'outside-admin@tenant.test', $managerRole, $otherTenant);
        $storeEmployee = $this->createTenantUser('Store Scoped Employee', 'store-employee@tenant.test', $employeeRole, $context['tenant']);
        $outsideEmployee = $this->createTenantUser('Outside Scoped Employee', 'outside-employee@tenant.test', $employeeRole, $otherTenant);
        $storeCustomer = $this->createTenantCustomer('Store Scoped Customer', 'store-customer@tenant.test', $customerRole, $context['tenant']);
        $outsideCustomer = $this->createTenantCustomer('Outside Scoped Customer', 'outside-customer@tenant.test', $customerRole, $otherTenant);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $adminResponse = $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson('http://merchant.company.com/api/admin/administrator')
            ->assertOk();
        $adminIds = collect($adminResponse->json('data'))->pluck('id');
        $this->assertTrue($adminIds->contains($storeAdmin->id));
        $this->assertFalse($adminIds->contains($outsideAdmin->id));

        $employeeResponse = $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson('http://merchant.company.com/api/admin/employee?role_id='.LegacyRole::POS_OPERATOR)
            ->assertOk();
        $employeeIds = collect($employeeResponse->json('data'))->pluck('id');
        $this->assertTrue($employeeIds->contains($storeEmployee->id));
        $this->assertFalse($employeeIds->contains($outsideEmployee->id));

        $customerResponse = $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson('http://merchant.company.com/api/admin/customer')
            ->assertOk();
        $customerIds = collect($customerResponse->json('data'))->pluck('id');
        $this->assertTrue($customerIds->contains($storeCustomer->id));
        $this->assertFalse($customerIds->contains($outsideCustomer->id));

        $this
            ->withHeaders($this->tenantHeaders($context['tenant']->slug))
            ->getJson("http://merchant.company.com/api/admin/customer/show/{$outsideCustomer->id}")
            ->assertStatus(422);
    }

    private function createLegacyAdminUser(int $roleId, string $roleName, string $email): User
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            $role = new Role();
            $role->id = $roleId;
            $role->name = $roleName;
            $role->guard_name = 'sanctum';
            $role->save();
        }

        $user = User::factory()->create([
            'name' => ucfirst($roleName).' Workspace User',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => $roleName.'-workspace',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createMerchantContext(string $slug, ?array $permissions = null): array
    {
        $role = $this->createLegacyRoleWithPermissions(LegacyRole::MANAGER, 'manager', $permissions ?? [
            'products',
            'products_show',
        ]);

        $user = User::factory()->create([
            'name' => 'Merchant Legacy User',
            'email' => "{$slug}@merchant.test",
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

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 4)).strtoupper(Str::random(4)),
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => "{$slug}.company.com",
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $tenant;
    }

    private function createTenantUser(string $name, string $email, Role $legacyRole, Tenant $tenant): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => Str::slug($name).'-'.Str::random(5),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($legacyRole);

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $this->merchantStaffPlatformRole()->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }

    private function createTenantCustomer(string $name, string $email, Role $legacyRole, Tenant $tenant): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => Str::slug($name).'-'.Str::random(5),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($legacyRole);

        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'legacy_user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'password' => 'password',
            'status' => 5,
            'is_guest' => false,
            'email_verified_at' => now(),
        ]);

        return $user;
    }

    private function merchantStaffPlatformRole(): PlatformRole
    {
        return PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_staff'],
            ['name' => 'Merchant Staff', 'scope' => 'merchant', 'is_system' => true]
        );
    }

    private function createLegacyRoleWithPermissions(int $roleId, string $roleName, array $permissions): Role
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            $role = new Role();
            $role->id = $roleId;
            $role->name = $roleName;
            $role->guard_name = 'sanctum';
            $role->save();
        }

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]));
        }

        return $role;
    }

    private function tenantHeaders(string $tenantSlug): array
    {
        return [
            'x-api-key' => 'testing-key',
            'x-localization' => 'en',
            'X-Tenant-Slug' => $tenantSlug,
        ];
    }
}
