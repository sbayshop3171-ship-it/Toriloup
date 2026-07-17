<?php

namespace Tests\Feature;

use App\Enums\Ask;
use App\Enums\Role as LegacyRole;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\User;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSurfacePasswordResetTest extends TestCase
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

        Settings::group('site')->set([
            'site_email_verification' => 5,
            'site_phone_verification' => 10,
        ]);

        Settings::group('otp')->set([
            'otp_expire_time' => 10,
        ]);
    }

    public function test_legacy_auth_routes_are_blocked_on_admin_hosts(): void
    {
        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/auth/forgot-password', [
                'email' => 'owner@test.com',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Legacy auth endpoints are disabled on admin hosts. Use the surface-specific auth routes instead.');

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/auth/login', [
                'email' => 'merchant@test.com',
                'password' => 'password',
            ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Legacy auth endpoints are disabled on admin hosts. Use the surface-specific auth routes instead.');
    }

    public function test_platform_password_reset_returns_platform_surface_token(): void
    {
        $owner = $this->createUserWithRole(LegacyRole::ADMIN, 'admin', 'owner-reset@test.com');
        $this->seedVerifiedEmailReset($owner->email);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/platform/auth/forgot-password/reset-password', [
                'email' => $owner->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'platform')
            ->assertJsonPath('status', true);

        $this->assertTrue(Hash::check('new-password', $owner->fresh()->password));
        $this->assertSame('platform_auth_token', $owner->tokens()->latest('id')->first()?->name);
        $this->assertSame(['surface:platform'], $owner->tokens()->latest('id')->first()?->abilities);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $owner->email,
        ]);
    }

    public function test_merchant_password_reset_returns_merchant_surface_token_and_membership_context(): void
    {
        $merchant = $this->createUserWithRole(LegacyRole::MANAGER, 'manager', 'merchant-reset@test.com');
        $tenant = $this->createTenantWithMembership($merchant, 'merchant-reset-store');
        $this->seedVerifiedEmailReset($merchant->email);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/forgot-password/reset-password', [
                'email' => $merchant->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'merchant')
            ->assertJsonPath('status', true)
            ->assertJsonPath('current_tenant.tenant.slug', $tenant->slug)
            ->assertJsonCount(1, 'tenants');

        $this->assertTrue(Hash::check('new-password', $merchant->fresh()->password));
        $this->assertSame('merchant_auth_token', $merchant->tokens()->latest('id')->first()?->name);
        $this->assertSame(['surface:merchant'], $merchant->tokens()->latest('id')->first()?->abilities);
    }

    public function test_platform_password_reset_rejects_merchant_account(): void
    {
        $merchant = $this->createUserWithRole(LegacyRole::MANAGER, 'manager', 'merchant-on-owner@test.com');
        $this->createTenantWithMembership($merchant, 'merchant-owner-mismatch');
        $this->seedVerifiedEmailReset($merchant->email);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/platform/auth/forgot-password/reset-password', [
                'email' => $merchant->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.validation', 'Only owner-level admin accounts can use platform password reset.');
    }

    public function test_merchant_password_reset_rejects_owner_account(): void
    {
        $owner = $this->createUserWithRole(LegacyRole::ADMIN, 'admin', 'owner-on-merchant@test.com');
        $this->seedVerifiedEmailReset($owner->email);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/forgot-password/reset-password', [
                'email' => $owner->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertForbidden()
            ->assertJsonPath('errors.validation', 'Owner accounts must reset through the owner workspace only.');
    }

    public function test_password_reset_requires_verified_email_challenge(): void
    {
        $owner = $this->createUserWithRole(LegacyRole::ADMIN, 'admin', 'owner-unverified-reset@test.com');

        DB::table('password_reset_tokens')->insert([
            'email' => $owner->email,
            'token' => '1234',
            'is_verified' => Ask::NO,
            'created_at' => now(),
        ]);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://owner.company.com/api/platform/auth/forgot-password/reset-password', [
                'email' => $owner->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.validation', 'Password reset verification is required before changing the password.');
    }

    private function createUserWithRole(int $roleId, string $roleName, string $email): User
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            $role = new Role();
            $role->id = $roleId;
            $role->name = $roleName;
            $role->guard_name = 'web';
            $role->save();
        }

        $user = User::factory()->create([
            'name' => Str::headline($roleName).' Reset User',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => 5,
            'username' => Str::slug($roleName).'-reset-'.Str::random(4),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createTenantWithMembership(User $user, string $slug): Tenant
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

        $platformRole = PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_owner'],
            ['name' => 'Merchant Owner', 'scope' => 'merchant', 'is_system' => true]
        );

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $tenant;
    }

    private function seedVerifiedEmailReset(string $email): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => '1234',
            'is_verified' => Ask::YES,
            'created_at' => now(),
        ]);
    }
}
