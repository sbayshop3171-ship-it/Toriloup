<?php

namespace App\Services\Saas;

use App\Enums\Ask;
use App\Enums\Role;
use App\Enums\Status;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFeatureFlag;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class TenantProvisioningService
{
    public function __construct(
        private readonly PlatformRoleRegistryService $platformRoleRegistryService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly SubscriptionManagerService $subscriptionManagerService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{user: User, tenant: Tenant, domain: TenantDomain, checks: array<string, bool>}
     */
    public function registerMerchant(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $merchantRole = $this->ensureManagerRole();
            $tenantRole = $this->platformRoleRegistryService->merchantOwnerRole();
            $storeSlug = $this->resolveStoreSlug($payload);

            $user = User::query()->create([
                'name' => $payload['owner_name'],
                'username' => $this->buildUsername($payload['owner_name'], $storeSlug),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'country_code' => $payload['country_code'] ?? null,
                'email_verified_at' => now(),
                'is_guest' => Ask::NO,
                'status' => Status::ACTIVE,
                'password' => Hash::make((string) $payload['password']),
            ]);
            $user->assignRole($merchantRole);

            $tenant = Tenant::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $payload['store_name'],
                'legal_name' => $payload['legal_name'] ?? null,
                'slug' => $storeSlug,
                'store_code' => $this->buildStoreCode($storeSlug),
                'status' => 'draft',
                'plan_code' => $payload['plan_code'] ?? 'starter',
                'onboarding_status' => 'pending',
                'primary_locale' => $payload['primary_locale'] ?? 'en',
                'primary_currency_code' => $payload['primary_currency_code'] ?? 'USD',
                'timezone' => $payload['timezone'] ?? 'UTC',
                'country_code' => $payload['country_code'] ?? null,
                'contact_email' => $payload['email'] ?? null,
                'contact_phone' => $payload['phone'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            $domain = TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'hostname' => sprintf('%s.%s', $tenant->slug, config('saas.fallback_subdomain_suffix')),
                'domain_type' => 'subdomain',
                'is_primary' => true,
                'is_fallback' => true,
                'ssl_status' => 'active',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'last_checked_at' => now(),
            ]);

            TenantMember::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $tenantRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->tenantSettingsService->seedDefaultsForTenant($tenant, [
                'company_name' => $tenant->name,
                'company_email' => $tenant->contact_email,
                'company_phone' => $tenant->contact_phone,
                'company_calling_code' => $payload['country_code'] ?? null,
                'company_country_code' => $payload['country_code'] ?? null,
                'site_online_payment_gateway' => 5,
                'site_cash_on_delivery' => 5,
                'shipping_setup_method' => 5,
            ]);

            $this->seedCommerceDefaults($tenant, $user);
            $this->subscriptionManagerService->assignPlanToTenant(
                $tenant,
                $tenant->plan_code ?? 'starter',
                'monthly',
                $user,
                ['source' => 'merchant_register']
            );

            $checks = $this->evaluateAutoLiveChecks($tenant);

            if (!in_array(false, $checks, true)) {
                $tenant->forceFill([
                    'status' => 'active',
                    'onboarding_status' => 'basic_complete',
                    'approved_by_user_id' => $user->id,
                    'approved_at' => now(),
                    'launched_at' => now(),
                ])->save();
            }

            return [
                'user' => $user,
                'tenant' => $tenant->fresh(),
                'domain' => $domain->fresh(),
                'checks' => $checks,
            ];
        });
    }

    public function syncShadowCustomer(User $user, Tenant $tenant): Customer
    {
        $identity = ['tenant_id' => $tenant->id];

        if (filled($user->email)) {
            $identity['email'] = $user->email;
        } else {
            $identity['phone'] = $user->phone;
            $identity['country_code'] = $user->country_code;
        }

        return Customer::query()->updateOrCreate(
            $identity,
            [
                'legacy_user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'name' => $user->name,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'password' => $user->password,
                'status' => 1,
                'is_guest' => (bool) $user->is_guest,
                'email_verified_at' => $user->email_verified_at,
            ]
        );
    }

    /**
     * @return array<string, bool>
     */
    public function evaluateAutoLiveChecks(Tenant $tenant): array
    {
        $settings = $this->tenantSettingsService->mergedForTenant($tenant);

        return [
            'unique_store_slug' => filled($tenant->slug),
            'starter_preset_assigned' => $tenant->domains()->exists(),
            'payment_method_active' => TenantPaymentMethod::query()->where('tenant_id', $tenant->id)->where('status', true)->exists(),
            'shipping_active' => filled($settings['shipping_setup_method'] ?? null),
            'no_owner_suspension' => $tenant->status !== 'suspended',
            'no_fraud_block' => true,
            'verified_contact' => filled($tenant->contact_email) || filled($tenant->contact_phone),
        ];
    }

    private function ensureManagerRole(): SpatieRole
    {
        $role = SpatieRole::query()->find(Role::MANAGER);

        if ($role !== null) {
            return $role;
        }

        $role = new SpatieRole();
        $role->id = Role::MANAGER;
        $role->name = 'manager';
        $role->guard_name = 'web';
        $role->save();

        return $role;
    }

    private function buildUsername(string $ownerName, string $storeSlug): string
    {
        return Str::slug($ownerName).'-'.$storeSlug.'-'.Str::lower(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveStoreSlug(array $payload): string
    {
        $source = filled($payload['store_slug'] ?? null)
            ? (string) $payload['store_slug']
            : (string) $payload['store_name'];

        $baseSlug = Str::slug($source);

        if ($baseSlug === '') {
            $baseSlug = 'store';
        }

        $baseSlug = Str::limit($baseSlug, 110, '');
        $slug = $baseSlug;
        $counter = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $suffix = '-'.$counter;
            $slug = Str::limit($baseSlug, 120 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $slug;
    }

    private function buildStoreCode(string $storeSlug): string
    {
        return strtoupper(Str::substr(Str::slug($storeSlug, ''), 0, 6)).str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function seedCommerceDefaults(Tenant $tenant, User $actor): void
    {
        TenantPaymentMethod::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider_code' => 'cash_on_delivery',
            ],
            [
                'display_name' => 'Cash on Delivery',
                'status' => true,
                'checkout_label' => 'Pay with cash on delivery',
                'fee_type' => 'none',
                'fee_value' => null,
                'sort_order' => 1,
                'config_json' => ['managed_by' => 'owner'],
            ]
        );

        TenantFeatureFlag::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_code' => 'simple_mode',
            ],
            [
                'status' => true,
                'source' => 'platform_default',
                'updated_by_user_id' => $actor->id,
            ]
        );

        TenantFeatureFlag::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_code' => 'advanced_mode',
            ],
            [
                'status' => false,
                'source' => 'platform_default',
                'updated_by_user_id' => $actor->id,
            ]
        );
    }
}
