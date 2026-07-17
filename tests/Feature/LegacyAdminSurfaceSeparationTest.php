<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    private function createLegacyAdminUser(int $roleId, string $roleName, string $email): User
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
}
