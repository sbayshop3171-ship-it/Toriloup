<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Models\Barcode;
use App\Models\PlatformPlan;
use App\Models\PlatformProvider;
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
use App\Services\Saas\SubscriptionManagerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PDOException;
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

    public function test_default_billing_provider_falls_back_when_live_provider_enum_is_legacy(): void
    {
        PlatformProvider::creating(function (PlatformProvider $provider): void {
            if ($provider->provider_type !== 'saas_billing') {
                return;
            }

            throw new QueryException(
                'mysql',
                'insert into `platform_providers` (`provider_type`) values (?)',
                ['saas_billing'],
                new PDOException("SQLSTATE[01000]: Warning: 1265 Data truncated for column 'provider_type' at row 1")
            );
        });

        try {
            /** @var SubscriptionManagerService $service */
            $service = app(SubscriptionManagerService::class);
            $provider = $service->ensureDefaultBillingProvider();

            $this->assertSame('manual', $provider->provider_code);
            $this->assertSame('payment', $provider->provider_type);
            $this->assertSame($provider->id, $service->activeBillingProvider()?->id);
        } finally {
            PlatformProvider::flushEventListeners();
        }
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
            ->assertJsonPath('data.status', 'pending_activation')
            ->assertJsonPath('data.invoices.0.status', 'open');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan_code' => 'starter',
        ]);

        $markPaidResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/subscriptions/{$subscriptionId}/invoices/{$invoiceId}/mark-paid");

        $markPaidResponse
            ->assertOk()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.subscription.plan.code', 'pro-lite');

        $this->assertDatabaseHas('tenant_subscription_invoices', [
            'id' => $invoiceId,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'plan_code' => 'pro-lite',
        ]);

        $assignResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->getJson('http://owner.company.com/api/platform/subscriptions?tenant_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.0.plan.code', 'pro-lite');
    }

    public function test_merchant_checkout_keeps_current_plan_until_payment_success(): void
    {
        $context = $this->createMerchantContext('checkout-store');
        $merchantToken = $this->merchantToken($context['user']);

        $plansResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/billing/plans');

        $plansResponse
            ->assertOk()
            ->assertJsonFragment(['code' => 'starter'])
            ->assertJsonFragment(['code' => 'basic']);

        $checkoutResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/billing/checkout', [
                'plan_code' => 'basic',
                'billing_interval' => 'monthly',
            ]);

        $checkoutResponse
            ->assertOk()
            ->assertJsonPath('data.mode', 'checkout')
            ->assertJsonPath('data.subscription.status', 'pending_activation')
            ->assertJsonPath('data.checkout_session.status', 'pending');

        $this->assertDatabaseHas('tenants', [
            'id' => $context['tenant']->id,
            'plan_code' => 'starter',
        ]);

        $cancelResponse = $this
            ->postJson('http://owner.company.com/api/platform/billing/providers/manual/webhook', [
                'session_token' => $checkoutResponse->json('data.checkout_session.session_token'),
                'status' => 'failed',
            ]);

        $cancelResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.subscription.status', 'cancelled')
            ->assertJsonPath('data.invoice.status', 'void');

        $this->assertDatabaseHas('tenants', [
            'id' => $context['tenant']->id,
            'plan_code' => 'starter',
        ]);

        $paidCheckoutResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/billing/checkout', [
                'plan_code' => 'basic',
                'billing_interval' => 'monthly',
            ]);

        $paidCheckoutResponse
            ->assertOk()
            ->assertJsonPath('data.mode', 'checkout');

        $paidResponse = $this
            ->postJson('http://owner.company.com/api/platform/billing/providers/manual/webhook', [
                'session_token' => $paidCheckoutResponse->json('data.checkout_session.session_token'),
                'status' => 'paid',
            ]);

        $paidResponse
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.subscription.status', 'trialing')
            ->assertJsonPath('data.subscription.plan.code', 'basic')
            ->assertJsonPath('data.invoice.status', 'paid');

        $this->assertDatabaseHas('tenants', [
            'id' => $context['tenant']->id,
            'plan_code' => 'basic',
        ]);
    }

    public function test_default_plan_backfill_does_not_overwrite_owner_catalog_edits(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->putJson('http://owner.company.com/api/platform/plans/basic', [
                'name' => 'Owner Basic',
                'short_description' => 'Owner managed pricing',
                'status' => 'active',
                'is_public' => true,
                'currency_code' => 'USD',
                'prices' => [
                    'monthly' => 77,
                    'semiannual' => 420,
                    'yearly' => 770,
                ],
                'limits' => [
                    ['key' => 'products', 'value' => 77, 'is_unlimited' => false],
                ],
                'features' => [
                    ['code' => 'report_exports', 'label' => 'Report exports', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Owner Basic')
            ->assertJsonPath('data.prices.monthly', '77.00');

        $context = $this->createMerchantContext('owner-catalog-preserve');
        $merchantToken = $this->merchantToken($context['user']);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/billing/summary')
            ->assertOk();

        $plan = PlatformPlan::query()
            ->with(['prices', 'limits', 'features'])
            ->where('code', 'basic')
            ->firstOrFail();

        $this->assertSame('Owner Basic', $plan->name);
        $this->assertSame('Owner managed pricing', $plan->short_description);
        $this->assertSame('77.00', (string) $plan->monthly_price);
        $this->assertSame('420.00', (string) $plan->prices->firstWhere('billing_interval', 'semiannual')->price_amount);
        $this->assertSame(77, $plan->limits->firstWhere('limit_key', 'products')->limit_value);
        $this->assertSame('1', $plan->features->firstWhere('feature_code', 'report_exports')->feature_value);
    }

    public function test_locked_feature_routes_return_upgrade_required(): void
    {
        $context = $this->createMerchantContext('locked-feature-store');
        $merchantToken = $this->merchantToken($context['user']);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/return-orders')
            ->assertStatus(402)
            ->assertJsonPath('code', 'upgrade_required')
            ->assertJsonPath('feature_code', 'returns');
    }

    public function test_merchant_billing_summary_and_quota_limits_are_enforced(): void
    {
        $owner = $this->createPlatformOwner();
        $platformToken = $this->platformToken($owner);
        $context = $this->createMerchantContext('quota-store');

        $assignResponse = $this
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
                'features' => [
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true],
                ],
            ])
            ->assertOk();

        $assignResponse = $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/tenants/{$context['tenant']->id}/subscription", [
                'plan_code' => 'micro',
                'billing_interval' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_activation')
            ->assertJsonPath('data.invoices.0.status', 'open');

        $subscriptionId = (int) $assignResponse->json('data.id');
        $invoiceId = (int) $assignResponse->json('data.invoices.0.id');

        $this
            ->withToken($platformToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson("http://owner.company.com/api/platform/subscriptions/{$subscriptionId}/invoices/{$invoiceId}/mark-paid")
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'active');

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

    public function test_disabled_public_plan_catalog_grants_full_access_and_hides_plan_cards(): void
    {
        /** @var SubscriptionManagerService $service */
        $service = app(SubscriptionManagerService::class);
        $service->ensureDefaultPlans();

        PlatformPlan::query()->update([
            'status' => 'draft',
            'is_public' => false,
        ]);

        $context = $this->createMerchantContext('relaxed-billing-store');
        $merchantToken = $this->merchantToken($context['user']);

        $this->assertFalse($service->billingCatalogEnforced());
        $this->assertTrue($service->hasFeatureAccess($context['tenant'], 'returns'));

        $summaryResponse = $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/billing/summary');

        $summaryResponse
            ->assertOk()
            ->assertJsonPath('catalog.has_active_public_plans', false)
            ->assertJsonPath('catalog.enforced', false)
            ->assertJsonPath('features.mode', 'catalog_disabled')
            ->assertJsonPath('features.features.returns.status', true)
            ->assertJsonPath('usage.products.unlimited', true);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/billing/plans')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_grandfathered_tenant_keeps_full_access_until_plan_change(): void
    {
        /** @var SubscriptionManagerService $service */
        $service = app(SubscriptionManagerService::class);
        $service->ensureDefaultPlans();

        $context = $this->createMerchantContext('grandfathered-billing-store');
        $context['tenant']->forceFill([
            'billing_exempt_until_plan_change' => true,
            'billing_grandfathered_at' => now(),
        ])->save();

        $starterPlan = PlatformPlan::query()->where('code', 'starter')->firstOrFail();
        TenantSubscription::query()->create([
            'tenant_id' => $context['tenant']->id,
            'plan_id' => $starterPlan->id,
            'plan_code_snapshot' => $starterPlan->code,
            'plan_name_snapshot' => $starterPlan->name,
            'status' => 'past_due',
            'billing_interval' => 'monthly',
            'currency_code' => $starterPlan->currency_code,
            'price_amount' => 0,
            'starts_at' => now()->subMonths(2),
            'current_period_starts_at' => now()->subMonth(),
            'current_period_ends_at' => now()->subDay(),
            'grace_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($service->billingCatalogEnforced());
        $this->assertTrue($service->hasFeatureAccess($context['tenant']->fresh(), 'returns'));
        $this->assertSame('grandfathered', $service->featureSummary($context['tenant']->fresh())['mode']);
        $this->assertSame('past_due', $service->currentSubscription($context['tenant']->fresh())?->status);
        $this->assertTrue($context['tenant']->fresh()->billing_exempt_until_plan_change);

        $service->assignPlanToTenant(
            $context['tenant']->fresh(),
            'starter',
            'monthly',
            $context['user'],
            ['source' => 'test_plan_change']
        );

        $tenant = $context['tenant']->fresh();

        $this->assertFalse($tenant->billing_exempt_until_plan_change);
        $this->assertNull($tenant->billing_grandfathered_at);
        $this->assertFalse($service->hasFeatureAccess($tenant, 'returns'));
    }

    public function test_registration_without_public_free_plan_requires_plan_without_grandfathering(): void
    {
        /** @var SubscriptionManagerService $service */
        $service = app(SubscriptionManagerService::class);
        $service->ensureDefaultPlans();

        PlatformPlan::query()
            ->where('monthly_price', '<=', 0)
            ->update(['status' => 'draft']);

        PlatformPlan::query()
            ->where('code', 'basic')
            ->update([
                'status' => 'active',
                'is_public' => true,
            ]);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->postJson('http://merchant.company.com/api/merchant/auth/register', [
                'owner_name' => 'Paid Only Merchant',
                'store_name' => 'Paid Only Store',
                'email' => 'paid-only-merchant@example.com',
                'password' => 'password',
            ]);

        $tenantId = (int) $response->json('tenant.id');
        $tenantSlug = (string) $response->json('tenant.slug');
        $merchantToken = (string) $response->json('token');

        $response
            ->assertCreated()
            ->assertJsonPath('tenant.plan_code', null);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenantId,
            'plan_code' => null,
            'billing_exempt_until_plan_change' => false,
        ]);

        $this->assertDatabaseMissing('tenant_subscriptions', [
            'tenant_id' => $tenantId,
        ]);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $tenantSlug)
            ->getJson('http://merchant.company.com/api/merchant/billing/summary')
            ->assertOk()
            ->assertJsonPath('catalog.has_active_public_plans', true)
            ->assertJsonPath('catalog.enforced', true)
            ->assertJsonPath('catalog.billing_exempt', false)
            ->assertJsonPath('features.mode', 'no_active_subscription')
            ->assertJsonPath('features.features.returns.status', false);

        $this
            ->withToken($merchantToken)
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $tenantSlug)
            ->getJson('http://merchant.company.com/api/merchant/billing/plans')
            ->assertOk()
            ->assertJsonFragment(['code' => 'basic']);
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
