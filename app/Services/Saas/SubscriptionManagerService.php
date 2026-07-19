<?php

namespace App\Services\Saas;

use App\Models\PlatformPlan;
use App\Models\PlatformPlanFeature;
use App\Models\PlatformPlanLimit;
use App\Models\PlatformPlanPrice;
use App\Models\PlatformProvider;
use App\Models\Product;
use App\Models\SubscriptionCheckoutSession;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantFeatureFlag;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionManagerService
{
    private const FALLBACK_PLAN_CODE = 'starter';

    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultPlans(): array
    {
        $plans = [
            'starter' => [
                'name' => 'Free',
                'short_description' => 'Start selling today.',
                'description' => 'Free fallback plan for merchants who want to launch without a paid subscription.',
                'status' => 'active',
                'is_public' => true,
                'display_order' => 1,
                'is_recommended' => false,
                'badge_label' => 'Current',
                'currency_code' => 'USD',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'prices' => [
                    'monthly' => 0,
                    'semiannual' => 0,
                    'yearly' => 0,
                ],
                'trial_days' => 0,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
                'metadata_json' => ['tier' => 'free', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => 20, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 0, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 1, 'is_unlimited' => false],
                ],
                'features' => [
                    ['code' => 'fee_physical', 'label' => 'Physical order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '5', 'sort_order' => 10],
                    ['code' => 'fee_digital', 'label' => 'Digital order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '10', 'sort_order' => 20],
                    ['code' => 'fee_resell', 'label' => 'Resell order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '3', 'sort_order' => 30],
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => false, 'sort_order' => 40],
                    ['code' => 'preset_themes', 'label' => 'Preset themes', 'group' => 'Store & Branding', 'type' => 'integer', 'value' => '2', 'sort_order' => 50],
                    ['code' => 'theme_builder', 'label' => 'Theme builder', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => false, 'sort_order' => 60],
                    ['code' => 'campaigns', 'label' => 'Campaigns & promos', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => false, 'sort_order' => 70],
                    ['code' => 'report_exports', 'label' => 'Report exports', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => false, 'sort_order' => 80],
                    ['code' => 'advanced_stock', 'label' => 'Advanced stock workflows', 'group' => 'Operations', 'type' => 'boolean', 'value' => false, 'sort_order' => 90],
                    ['code' => 'returns', 'label' => 'Returns & refunds', 'group' => 'Operations', 'type' => 'boolean', 'value' => false, 'sort_order' => 100],
                    ['code' => 'pos', 'label' => 'POS', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 110],
                    ['code' => 'external_gateways', 'label' => 'External payment gateways', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => false, 'sort_order' => 120],
                    ['code' => 'third_party_couriers', 'label' => 'Third-party couriers', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => false, 'sort_order' => 130],
                ],
            ],
            'basic' => [
                'name' => 'Basic',
                'short_description' => 'For growing stores.',
                'description' => 'Affordable plan with custom domains, report access, and more catalog capacity.',
                'status' => 'active',
                'is_public' => true,
                'display_order' => 2,
                'is_recommended' => false,
                'badge_label' => null,
                'currency_code' => 'USD',
                'monthly_price' => 19,
                'yearly_price' => 189,
                'prices' => [
                    'monthly' => 19,
                    'semiannual' => 102,
                    'yearly' => 189,
                ],
                'trial_days' => 3,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
                'metadata_json' => ['tier' => 'basic', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => 250, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 1, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 3, 'is_unlimited' => false],
                ],
                'features' => [
                    ['code' => 'fee_physical', 'label' => 'Physical order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '2', 'sort_order' => 10],
                    ['code' => 'fee_digital', 'label' => 'Digital order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '6', 'sort_order' => 20],
                    ['code' => 'fee_resell', 'label' => 'Resell order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '2', 'sort_order' => 30],
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true, 'sort_order' => 40],
                    ['code' => 'preset_themes', 'label' => 'Preset themes', 'group' => 'Store & Branding', 'type' => 'text', 'value' => 'Unlimited', 'sort_order' => 50],
                    ['code' => 'theme_builder', 'label' => 'Theme builder', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => false, 'sort_order' => 60],
                    ['code' => 'campaigns', 'label' => 'Campaigns & promos', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => false, 'sort_order' => 70],
                    ['code' => 'report_exports', 'label' => 'Report exports', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true, 'sort_order' => 80],
                    ['code' => 'advanced_stock', 'label' => 'Advanced stock workflows', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 90],
                    ['code' => 'returns', 'label' => 'Returns & refunds', 'group' => 'Operations', 'type' => 'boolean', 'value' => false, 'sort_order' => 100],
                    ['code' => 'pos', 'label' => 'POS', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 110],
                    ['code' => 'external_gateways', 'label' => 'External payment gateways', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => false, 'sort_order' => 120],
                    ['code' => 'third_party_couriers', 'label' => 'Third-party couriers', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => true, 'sort_order' => 130],
                ],
            ],
            'premium' => [
                'name' => 'Premium',
                'short_description' => 'Most popular for scaling brands.',
                'description' => 'Balanced plan with campaign, returns, and branded storefront unlocks.',
                'status' => 'active',
                'is_public' => true,
                'display_order' => 3,
                'is_recommended' => true,
                'badge_label' => 'Most Popular',
                'currency_code' => 'USD',
                'monthly_price' => 49,
                'yearly_price' => 479,
                'prices' => [
                    'monthly' => 49,
                    'semiannual' => 264,
                    'yearly' => 479,
                ],
                'trial_days' => 7,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
                'metadata_json' => ['tier' => 'premium', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => 1000, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 3, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 10, 'is_unlimited' => false],
                ],
                'features' => [
                    ['code' => 'fee_physical', 'label' => 'Physical order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '0', 'sort_order' => 10],
                    ['code' => 'fee_digital', 'label' => 'Digital order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '3', 'sort_order' => 20],
                    ['code' => 'fee_resell', 'label' => 'Resell order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '1', 'sort_order' => 30],
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true, 'sort_order' => 40],
                    ['code' => 'preset_themes', 'label' => 'Preset themes', 'group' => 'Store & Branding', 'type' => 'text', 'value' => 'Unlimited', 'sort_order' => 50],
                    ['code' => 'theme_builder', 'label' => 'Theme builder', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true, 'sort_order' => 60],
                    ['code' => 'campaigns', 'label' => 'Campaigns & promos', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true, 'sort_order' => 70],
                    ['code' => 'report_exports', 'label' => 'Report exports', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true, 'sort_order' => 80],
                    ['code' => 'advanced_stock', 'label' => 'Advanced stock workflows', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 90],
                    ['code' => 'returns', 'label' => 'Returns & refunds', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 100],
                    ['code' => 'pos', 'label' => 'POS', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 110],
                    ['code' => 'external_gateways', 'label' => 'External payment gateways', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => true, 'sort_order' => 120],
                    ['code' => 'third_party_couriers', 'label' => 'Third-party couriers', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => true, 'sort_order' => 130],
                ],
            ],
            'advanced' => [
                'name' => 'Advanced',
                'short_description' => 'Everything unlocked.',
                'description' => 'Full-access plan with maximum capacity and zero platform fees.',
                'status' => 'active',
                'is_public' => true,
                'display_order' => 4,
                'is_recommended' => false,
                'badge_label' => null,
                'currency_code' => 'USD',
                'monthly_price' => 99,
                'yearly_price' => 959,
                'prices' => [
                    'monthly' => 99,
                    'semiannual' => 534,
                    'yearly' => 959,
                ],
                'trial_days' => 7,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
                'metadata_json' => ['tier' => 'advanced', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => null, 'is_unlimited' => true],
                    ['key' => 'custom_domains', 'value' => null, 'is_unlimited' => true],
                    ['key' => 'staff_members', 'value' => null, 'is_unlimited' => true],
                ],
                'features' => [
                    ['code' => 'fee_physical', 'label' => 'Physical order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '0', 'sort_order' => 10],
                    ['code' => 'fee_digital', 'label' => 'Digital order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '0', 'sort_order' => 20],
                    ['code' => 'fee_resell', 'label' => 'Resell order fee', 'group' => 'Fees per Order', 'type' => 'percent', 'value' => '0', 'sort_order' => 30],
                    ['code' => 'custom_domain', 'label' => 'Custom domain', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true, 'sort_order' => 40],
                    ['code' => 'preset_themes', 'label' => 'Preset themes', 'group' => 'Store & Branding', 'type' => 'text', 'value' => 'Unlimited', 'sort_order' => 50],
                    ['code' => 'theme_builder', 'label' => 'Theme builder', 'group' => 'Store & Branding', 'type' => 'boolean', 'value' => true, 'sort_order' => 60],
                    ['code' => 'campaigns', 'label' => 'Campaigns & promos', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true, 'sort_order' => 70],
                    ['code' => 'report_exports', 'label' => 'Report exports', 'group' => 'Marketing & Growth', 'type' => 'boolean', 'value' => true, 'sort_order' => 80],
                    ['code' => 'advanced_stock', 'label' => 'Advanced stock workflows', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 90],
                    ['code' => 'returns', 'label' => 'Returns & refunds', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 100],
                    ['code' => 'pos', 'label' => 'POS', 'group' => 'Operations', 'type' => 'boolean', 'value' => true, 'sort_order' => 110],
                    ['code' => 'external_gateways', 'label' => 'External payment gateways', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => true, 'sort_order' => 120],
                    ['code' => 'third_party_couriers', 'label' => 'Third-party couriers', 'group' => 'Payments & Delivery', 'type' => 'boolean', 'value' => true, 'sort_order' => 130],
                ],
            ],
        ];

        $plans['growth'] = array_replace($plans['premium'], [
            'name' => 'Growth',
            'short_description' => 'Legacy growth plan alias.',
            'description' => 'Compatibility alias for merchants that were previously assigned to the Growth plan.',
            'is_public' => false,
            'display_order' => 90,
            'is_recommended' => false,
            'badge_label' => 'Legacy',
            'metadata_json' => ['tier' => 'growth', 'managed' => true, 'legacy_alias_for' => 'premium'],
        ]);

        $plans['scale'] = array_replace($plans['advanced'], [
            'name' => 'Scale',
            'short_description' => 'Legacy scale plan alias.',
            'description' => 'Compatibility alias for merchants that were previously assigned to the Scale plan.',
            'is_public' => false,
            'display_order' => 91,
            'is_recommended' => false,
            'badge_label' => 'Legacy',
            'metadata_json' => ['tier' => 'scale', 'managed' => true, 'legacy_alias_for' => 'advanced'],
        ]);

        return $plans;
    }

    public function ensureDefaultPlans(): void
    {
        $this->ensureDefaultBillingProvider();

        foreach ($this->defaultPlans() as $code => $payload) {
            $plan = PlatformPlan::query()
                ->with(['limits', 'prices', 'features'])
                ->where('code', $code)
                ->first();

            if ($plan === null) {
                $this->upsertPlan($code, $payload, false);

                continue;
            }

            $this->backfillDefaultPlanCatalog($plan, $payload);
        }
    }

    /**
     * Keep owner-edited plans intact. Defaults only create missing catalog rows
     * or initialize legacy plans that existed before the billing catalog fields.
     *
     * @param  array<string, mixed>  $payload
     */
    private function backfillDefaultPlanCatalog(PlatformPlan $plan, array $payload): void
    {
        DB::transaction(function () use ($plan, $payload): void {
            $plan->loadMissing(['limits', 'prices', 'features']);

            $looksUninitialized = $plan->short_description === null
                && (int) $plan->display_order === 0
                && $plan->prices->isEmpty()
                && $plan->features->isEmpty();

            if ($looksUninitialized) {
                $plan->forceFill([
                    'name' => (string) Arr::get($payload, 'name', $plan->name),
                    'short_description' => Arr::get($payload, 'short_description'),
                    'description' => Arr::get($payload, 'description', $plan->description),
                    'status' => Arr::get($payload, 'status', $plan->status),
                    'is_public' => (bool) Arr::get($payload, 'is_public', $plan->is_public),
                    'display_order' => (int) Arr::get($payload, 'display_order', $plan->display_order),
                    'is_recommended' => (bool) Arr::get($payload, 'is_recommended', $plan->is_recommended),
                    'badge_label' => Arr::get($payload, 'badge_label', $plan->badge_label),
                    'currency_code' => Arr::get($payload, 'currency_code', $plan->currency_code),
                    'monthly_price' => Arr::get($payload, 'monthly_price', $plan->monthly_price),
                    'yearly_price' => Arr::get($payload, 'yearly_price', $plan->yearly_price),
                    'trial_days' => (int) Arr::get($payload, 'trial_days', $plan->trial_days),
                    'transaction_fee_type' => Arr::get($payload, 'transaction_fee_type', $plan->transaction_fee_type),
                    'transaction_fee_value' => Arr::get($payload, 'transaction_fee_value', $plan->transaction_fee_value),
                    'metadata_json' => Arr::get($payload, 'metadata_json', $plan->metadata_json),
                ])->save();

                $plan->refresh();
            } else {
                $updates = [];

                if ($plan->short_description === null && Arr::has($payload, 'short_description')) {
                    $updates['short_description'] = Arr::get($payload, 'short_description');
                }

                if ((int) $plan->display_order === 0 && Arr::has($payload, 'display_order')) {
                    $updates['display_order'] = (int) Arr::get($payload, 'display_order');
                }

                if ($plan->badge_label === null && Arr::get($payload, 'badge_label')) {
                    $updates['badge_label'] = Arr::get($payload, 'badge_label');
                }

                if ($updates !== []) {
                    $plan->forceFill($updates)->save();
                    $plan->refresh();
                }
            }

            $pricePayload = $looksUninitialized
                ? $payload
                : [
                    'monthly_price' => $plan->monthly_price,
                    'yearly_price' => $plan->yearly_price,
                ];

            $existingIntervals = $plan->prices->pluck('billing_interval')->all();

            foreach ($this->normalizePrices($pricePayload) as $interval => $amount) {
                if (in_array($interval, $existingIntervals, true)) {
                    continue;
                }

                PlatformPlanPrice::query()->create([
                    'plan_id' => $plan->id,
                    'billing_interval' => $interval,
                    'price_amount' => $amount,
                ]);
            }

            if ($plan->limits->isEmpty() && is_array(Arr::get($payload, 'limits'))) {
                $this->syncPlanLimits($plan, Arr::get($payload, 'limits'));
            }

            if ($plan->features->isEmpty() && is_array(Arr::get($payload, 'features'))) {
                $this->syncPlanFeatures($plan, Arr::get($payload, 'features'));
            }
        });
    }

    public function ensureDefaultBillingProvider(): PlatformProvider
    {
        try {
            return PlatformProvider::query()->firstOrCreate(
                [
                    'provider_type' => 'saas_billing',
                    'provider_code' => 'manual',
                ],
                $this->defaultBillingProviderAttributes()
            );
        } catch (QueryException $exception) {
            if (!$this->isUnsupportedSaasBillingProviderType($exception)) {
                throw $exception;
            }
        }

        $provider = PlatformProvider::query()->where('provider_code', 'manual')->first();

        if ($provider instanceof PlatformProvider) {
            return $provider;
        }

        return PlatformProvider::query()->create(array_merge(
            [
                'provider_type' => 'payment',
                'provider_code' => 'manual',
            ],
            $this->defaultBillingProviderAttributes()
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertPlan(string $code, array $payload, bool $syncSubscribers = true): PlatformPlan
    {
        $prices = $this->normalizePrices($payload);

        $plan = DB::transaction(function () use ($code, $payload, $prices): PlatformPlan {
            $plan = PlatformPlan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => (string) $payload['name'],
                    'short_description' => Arr::get($payload, 'short_description'),
                    'description' => Arr::get($payload, 'description'),
                    'status' => Arr::get($payload, 'status', 'draft'),
                    'is_public' => Arr::has($payload, 'visibility')
                        ? Arr::get($payload, 'visibility') === 'public'
                        : (bool) Arr::get($payload, 'is_public', true),
                    'display_order' => (int) Arr::get($payload, 'display_order', 0),
                    'is_recommended' => (bool) Arr::get($payload, 'is_recommended', false),
                    'badge_label' => Arr::get($payload, 'badge_label'),
                    'currency_code' => Arr::get($payload, 'currency_code', 'USD'),
                    'monthly_price' => $prices['monthly'],
                    'yearly_price' => $prices['yearly'],
                    'trial_days' => (int) Arr::get($payload, 'trial_days', 0),
                    'transaction_fee_type' => Arr::get($payload, 'transaction_fee_type', 'none'),
                    'transaction_fee_value' => Arr::get($payload, 'transaction_fee_value'),
                    'metadata_json' => Arr::get($payload, 'metadata_json'),
                ]
            );

            $this->syncPlanPrices($plan, $prices);

            if (array_key_exists('limits', $payload) && is_array($payload['limits'])) {
                $this->syncPlanLimits($plan, $payload['limits']);
            }

            if (array_key_exists('features', $payload) && is_array($payload['features'])) {
                $this->syncPlanFeatures($plan, $payload['features']);
            }

            return $plan->fresh(['limits', 'prices', 'features']);
        });

        if ($syncSubscribers) {
            $this->syncActiveTenantFeatureFlagsForPlan($plan);
        }

        return $plan;
    }

    public function currentSubscription(Tenant $tenant, bool $provisionIfMissing = true): ?TenantSubscription
    {
        $this->ensureDefaultPlans();

        $subscription = TenantSubscription::query()
            ->with($this->subscriptionRelations())
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->orderByDesc('id')
            ->first();

        if ($subscription !== null && $subscription->status === 'past_due' && $subscription->grace_ends_at !== null && now()->greaterThan($subscription->grace_ends_at)) {
            $this->applyGraceFallback($subscription);

            $subscription = TenantSubscription::query()
                ->with($this->subscriptionRelations())
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->orderByDesc('id')
                ->first();
        }

        if ($subscription === null && $provisionIfMissing) {
            return $this->assignPlanToTenant($tenant, $tenant->plan_code ?: self::FALLBACK_PLAN_CODE);
        }

        return $subscription;
    }

    public function pendingSubscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::query()
            ->with($this->subscriptionRelations())
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending_activation')
            ->orderByDesc('id')
            ->first();
    }

    public function pendingCheckoutSession(Tenant $tenant): ?SubscriptionCheckoutSession
    {
        return SubscriptionCheckoutSession::query()
            ->with(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function assignPlanToTenant(
        Tenant $tenant,
        string $planCode,
        string $billingInterval = 'monthly',
        ?User $actor = null,
        array $metadata = [],
    ): TenantSubscription {
        $this->ensureDefaultPlans();

        $plan = PlatformPlan::query()->with(['limits', 'prices', 'features'])->where('code', $planCode)->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'The selected plan does not exist.',
            ]);
        }

        if ($plan->status !== 'active') {
            throw ValidationException::withMessages([
                'plan_code' => 'Only active plans can be assigned to tenants.',
            ]);
        }

        $price = $this->planPriceAmount($plan, $billingInterval);

        if ($price <= 0) {
            return $this->activatePlanImmediately(
                $tenant,
                $plan,
                $billingInterval,
                $actor,
                array_merge($metadata, ['source' => Arr::get($metadata, 'source', 'plan_assign')])
            );
        }

        return $this->createPendingActivation(
            $tenant,
            $plan,
            $billingInterval,
            $actor,
            array_merge($metadata, ['source' => Arr::get($metadata, 'source', 'plan_assign')])
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function beginMerchantCheckout(
        Tenant $tenant,
        string $planCode,
        string $billingInterval,
        ?User $actor = null,
        array $metadata = [],
    ): array {
        $currentSubscription = $this->currentSubscription($tenant);

        if (
            $currentSubscription !== null
            && in_array($currentSubscription->status, ['active', 'trialing'], true)
            && ($currentSubscription->plan?->code ?? $currentSubscription->plan_code_snapshot) === $planCode
            && $currentSubscription->billing_interval === $billingInterval
        ) {
            return [
                'mode' => 'current',
                'subscription' => $this->serializeSubscription($currentSubscription),
            ];
        }

        $plan = PlatformPlan::query()->with(['limits', 'prices', 'features'])->where('code', $planCode)->first();

        if ($plan === null || $plan->status !== 'active' || !$plan->is_public) {
            throw ValidationException::withMessages([
                'plan_code' => 'This plan is not available for merchant checkout.',
            ]);
        }

        $this->cancelPendingWorkflows($tenant);

        $subscription = $this->assignPlanToTenant(
            $tenant,
            $planCode,
            $billingInterval,
            $actor,
            array_merge($metadata, ['source' => 'merchant_checkout'])
        );

        $invoice = $subscription->invoices->sortByDesc('id')->first();

        if ($subscription->status !== 'pending_activation' || $invoice === null) {
            return [
                'mode' => 'immediate',
                'subscription' => $this->serializeSubscription($subscription),
            ];
        }

        $provider = $this->activeBillingProvider();

        if ($provider === null) {
            throw ValidationException::withMessages([
                'billing' => 'No active SaaS billing provider is configured by the platform owner.',
            ]);
        }

        $session = SubscriptionCheckoutSession::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_subscription_id' => $subscription->id,
            'tenant_subscription_invoice_id' => $invoice->id,
            'provider_code' => $provider->provider_code,
            'status' => 'pending',
            'session_token' => Str::lower(Str::random(40)),
            'external_reference' => 'sess_'.Str::lower(Str::random(16)),
            'return_url' => $this->merchantBillingUrl(['billing' => 'success']),
            'cancel_url' => $this->merchantBillingUrl(['billing' => 'cancelled']),
            'expires_at' => now()->addDay(),
            'metadata_json' => [
                'plan_code' => $planCode,
                'billing_interval' => $billingInterval,
            ],
        ]);

        $invoice->forceFill([
            'provider_code' => $provider->provider_code,
            'external_reference' => $session->external_reference,
        ])->save();

        return [
            'mode' => 'checkout',
            'subscription' => $this->serializeSubscription($subscription->fresh($this->subscriptionRelations())),
            'checkout_session' => $this->serializeCheckoutSession($session->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice'])),
            'checkout_url' => $this->hostedCheckoutUrl($provider->provider_code, $session),
        ];
    }

    public function completeCheckoutSession(SubscriptionCheckoutSession $session, string $status = 'paid'): SubscriptionCheckoutSession
    {
        if ($session->status === 'completed') {
            return $session->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice']);
        }

        if ($status !== 'paid') {
            return $this->cancelCheckoutSession($session, $status === 'failed' ? 'failed' : 'cancelled');
        }

        $invoice = $session->invoice;

        if ($invoice !== null) {
            $this->markInvoicePaid($invoice);
        }

        $session->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();

        return $session->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice']);
    }

    public function cancelCheckoutSession(SubscriptionCheckoutSession $session, string $status = 'cancelled'): SubscriptionCheckoutSession
    {
        return DB::transaction(function () use ($session, $status): SubscriptionCheckoutSession {
            if ($session->status === 'completed') {
                return $session->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice']);
            }

            $session->forceFill([
                'status' => $status,
            ])->save();

            if ($session->subscription !== null && $session->subscription->status === 'pending_activation') {
                $session->subscription->forceFill([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                ])->save();
            }

            if ($session->invoice !== null && $session->invoice->status === 'open') {
                $session->invoice->forceFill([
                    'status' => 'void',
                ])->save();
            }

            return $session->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice']);
        });
    }

    public function findCheckoutSessionByToken(string $token): ?SubscriptionCheckoutSession
    {
        return SubscriptionCheckoutSession::query()
            ->with(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice', 'tenant'])
            ->where('session_token', $token)
            ->first();
    }

    public function markInvoicePaid(TenantSubscriptionInvoice $invoice): TenantSubscriptionInvoice
    {
        return DB::transaction(function () use ($invoice): TenantSubscriptionInvoice {
            if ($invoice->status !== 'paid') {
                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();
            }

            $subscription = $invoice->subscription;

            if ($subscription !== null && $subscription->status === 'pending_activation') {
                $this->activatePendingSubscription($subscription);
            } elseif ($subscription !== null && $subscription->status === 'past_due') {
                $subscription->forceFill([
                    'status' => 'active',
                    'grace_ends_at' => null,
                ])->save();

                $this->syncTenantFeatureFlags($subscription->tenant, $subscription->plan);
            }

            SubscriptionCheckoutSession::query()
                ->where('tenant_subscription_invoice_id', $invoice->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

            return $invoice->fresh(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features']);
        });
    }

    /**
     * @return array<string, array<string, int|null|bool>>
     */
    public function usageSummary(Tenant $tenant): array
    {
        $subscription = $this->currentSubscription($tenant);
        $effectivePlan = $this->effectivePlanForTenant($tenant, $subscription);
        $limits = $effectivePlan?->limits->keyBy('limit_key') ?? collect();

        $usageValues = [
            'products' => Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->count(),
            'custom_domains' => TenantDomain::query()
                ->where('tenant_id', $tenant->id)
                ->where('domain_type', 'custom')
                ->count(),
            'staff_members' => TenantMember::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->count(),
        ];

        $summary = [];

        foreach ($usageValues as $key => $used) {
            /** @var PlatformPlanLimit|null $limit */
            $limit = $limits->get($key);
            $isUnlimited = $limit?->is_unlimited ?? false;
            $limitValue = $limit?->limit_value;

            $summary[$key] = [
                'used' => $used,
                'limit' => $isUnlimited ? null : $limitValue,
                'unlimited' => $isUnlimited,
                'remaining' => $isUnlimited || $limitValue === null ? null : max(0, $limitValue - $used),
            ];
        }

        return $summary;
    }

    public function enforceLimit(Tenant $tenant, string $limitKey, int $increment = 1, ?string $message = null): void
    {
        $usage = $this->usageSummary($tenant)[$limitKey] ?? null;

        if ($usage === null || $usage['unlimited'] === true || $usage['limit'] === null) {
            return;
        }

        if (($usage['used'] + $increment) > $usage['limit']) {
            throw ValidationException::withMessages([
                'plan' => $message ?? 'Current plan limit has been reached.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function featureSummary(Tenant $tenant): array
    {
        $subscription = $this->currentSubscription($tenant);
        $effectivePlan = $this->effectivePlanForTenant($tenant, $subscription);

        if ($effectivePlan === null) {
            return [];
        }

        $this->syncTenantFeatureFlags($tenant, $effectivePlan);

        $flags = TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('feature_code');

        $plan = $this->serializePlan($effectivePlan);
        $featureMap = [];
        $locked = [];

        foreach ($plan['features'] as $feature) {
            $status = $feature['type'] === 'boolean'
                ? (bool) ($flags->get($feature['code'])?->status ?? $feature['enabled'])
                : $feature['enabled'];

            $featureMap[$feature['code']] = [
                'code' => $feature['code'],
                'label' => $feature['label'],
                'group' => $feature['group'],
                'type' => $feature['type'],
                'status' => $status,
                'display_value' => $feature['type'] === 'boolean' ? ($status ? 'Unlocked' : 'Locked') : $feature['display_value'],
            ];

            if ($feature['type'] === 'boolean' && !$status) {
                $locked[] = $feature['code'];
            }
        }

        return [
            'plan_code' => $effectivePlan->code,
            'soft_locked' => $subscription?->status === 'past_due',
            'features' => $featureMap,
            'locked_features' => array_values(array_unique($locked)),
        ];
    }

    public function hasFeatureAccess(Tenant $tenant, string $featureCode): bool
    {
        $features = $this->featureSummary($tenant)['features'] ?? [];

        return (bool) ($features[$featureCode]['status'] ?? false);
    }

    public function enforceFeature(Tenant $tenant, string $featureCode, ?string $message = null): void
    {
        if ($this->hasFeatureAccess($tenant, $featureCode)) {
            return;
        }

        throw ValidationException::withMessages([
            'plan' => $message ?? 'This feature requires a higher plan.',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function merchantVisiblePlans(Tenant $tenant): array
    {
        $this->ensureDefaultPlans();

        $currentPlanCode = $this->currentSubscription($tenant, false)?->plan?->code ?? $tenant->plan_code ?? self::FALLBACK_PLAN_CODE;

        return PlatformPlan::query()
            ->with(['limits', 'prices', 'features'])
            ->where('status', 'active')
            ->where(function ($query) use ($currentPlanCode): void {
                $query->where('is_public', true)
                    ->orWhere('code', $currentPlanCode);
            })
            ->orderBy('display_order')
            ->orderBy('monthly_price')
            ->orderBy('name')
            ->get()
            ->map(fn (PlatformPlan $plan) => $this->serializePlan($plan))
            ->values()
            ->all();
    }

    public function hasPublicPaidPlans(): bool
    {
        $this->ensureDefaultPlans();

        return PlatformPlan::query()
            ->where('status', 'active')
            ->where('is_public', true)
            ->where('monthly_price', '>', 0)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePlan(PlatformPlan $plan): array
    {
        $plan->loadMissing(['limits', 'prices', 'features']);

        $prices = $plan->prices
            ->sortBy('billing_interval')
            ->mapWithKeys(fn (PlatformPlanPrice $price) => [$price->billing_interval => (string) $price->price_amount])
            ->all();

        $prices['monthly'] = $prices['monthly'] ?? number_format((float) $plan->monthly_price, 2, '.', '');
        $prices['semiannual'] = $prices['semiannual'] ?? number_format((float) $plan->monthly_price * 6, 2, '.', '');
        $prices['yearly'] = $prices['yearly'] ?? number_format((float) $plan->yearly_price, 2, '.', '');

        $features = $plan->features
            ->sortBy([
                ['compare_group', 'asc'],
                ['sort_order', 'asc'],
                ['feature_code', 'asc'],
            ])
            ->map(fn (PlatformPlanFeature $feature) => $this->serializeFeatureRow($feature))
            ->values();

        $limits = $plan->limits->map(fn (PlatformPlanLimit $limit) => [
            'key' => $limit->limit_key,
            'value' => $limit->limit_value,
            'is_unlimited' => $limit->is_unlimited,
            'display_value' => $limit->is_unlimited ? 'Unlimited' : (string) ($limit->limit_value ?? 0),
        ])->values();

        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'short_description' => $plan->short_description,
            'description' => $plan->description,
            'status' => $plan->status,
            'is_public' => $plan->is_public,
            'visibility' => $plan->is_public ? 'public' : 'hidden',
            'display_order' => $plan->display_order,
            'recommended' => $plan->is_recommended,
            'badge_label' => $plan->badge_label,
            'currency_code' => $plan->currency_code,
            'monthly_price' => number_format((float) $plan->monthly_price, 2, '.', ''),
            'yearly_price' => number_format((float) $plan->yearly_price, 2, '.', ''),
            'prices' => $prices,
            'trial_days' => $plan->trial_days,
            'transaction_fee_type' => $plan->transaction_fee_type,
            'transaction_fee_value' => $plan->transaction_fee_value,
            'metadata_json' => $plan->metadata_json,
            'limits' => $limits,
            'features' => $features,
            'compare_groups' => $this->groupFeatureRows($features, $limits),
            'subscribers_count' => $plan->subscriptions_count ?? $plan->subscriptions()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeSubscription(TenantSubscription $subscription): array
    {
        $subscription->loadMissing($this->subscriptionRelations());
        $effectivePlan = $subscription->tenant ? $this->effectivePlanForTenant($subscription->tenant, $subscription) : null;

        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'status' => $subscription->status,
            'billing_interval' => $subscription->billing_interval,
            'currency_code' => $subscription->currency_code,
            'price_amount' => number_format((float) $subscription->price_amount, 2, '.', ''),
            'trial_ends_at' => $subscription->trial_ends_at,
            'starts_at' => $subscription->starts_at,
            'current_period_starts_at' => $subscription->current_period_starts_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'grace_ends_at' => $subscription->grace_ends_at,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'ended_at' => $subscription->ended_at,
            'tenant' => $subscription->tenant?->only(['id', 'name', 'slug', 'status', 'plan_code']),
            'plan' => $subscription->plan ? $this->serializePlan($subscription->plan) : [
                'code' => $subscription->plan_code_snapshot,
                'name' => $subscription->plan_name_snapshot,
                'currency_code' => $subscription->currency_code,
            ],
            'effective_plan' => $effectivePlan ? $this->serializePlan($effectivePlan) : null,
            'soft_locked' => $subscription->status === 'past_due',
            'invoices' => $subscription->invoices
                ->sortByDesc('id')
                ->map(fn (TenantSubscriptionInvoice $invoice) => $this->serializeInvoice($invoice))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeInvoice(TenantSubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'status' => $invoice->status,
            'currency_code' => $invoice->currency_code,
            'subtotal_amount' => number_format((float) $invoice->subtotal_amount, 2, '.', ''),
            'transaction_fee_amount' => number_format((float) $invoice->transaction_fee_amount, 2, '.', ''),
            'total_amount' => number_format((float) $invoice->total_amount, 2, '.', ''),
            'provider_code' => $invoice->provider_code,
            'external_reference' => $invoice->external_reference,
            'issued_at' => $invoice->issued_at,
            'due_at' => $invoice->due_at,
            'paid_at' => $invoice->paid_at,
            'period_starts_at' => $invoice->period_starts_at,
            'period_ends_at' => $invoice->period_ends_at,
            'metadata_json' => $invoice->metadata_json,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCheckoutSession(SubscriptionCheckoutSession $session): array
    {
        $session->loadMissing(['subscription.plan.limits', 'subscription.plan.prices', 'subscription.plan.features', 'invoice']);

        return [
            'id' => $session->id,
            'provider_code' => $session->provider_code,
            'status' => $session->status,
            'session_token' => $session->session_token,
            'external_reference' => $session->external_reference,
            'return_url' => $session->return_url,
            'cancel_url' => $session->cancel_url,
            'completed_at' => $session->completed_at,
            'expires_at' => $session->expires_at,
            'subscription' => $session->subscription ? $this->serializeSubscription($session->subscription) : null,
            'invoice' => $session->invoice ? $this->serializeInvoice($session->invoice) : null,
        ];
    }

    public function activeBillingProvider(): ?PlatformProvider
    {
        $this->ensureDefaultBillingProvider();

        return PlatformProvider::query()
            ->where(function ($query): void {
                $query->where('provider_type', 'saas_billing')
                    ->orWhere(function ($legacyQuery): void {
                        $legacyQuery
                            ->where('provider_code', 'manual')
                            ->where('name', 'Manual SaaS Billing');
                    });
            })
            ->where('status', true)
            ->orderBy('name')
            ->first();
    }

    /**
     * @return array{name: string, status: bool, config_json: array<string, string>}
     */
    private function defaultBillingProviderAttributes(): array
    {
        return [
            'name' => 'Manual SaaS Billing',
            'status' => true,
            'config_json' => [
                'checkout_mode' => 'hosted_manual',
                'managed_by' => 'platform',
            ],
        ];
    }

    private function isUnsupportedSaasBillingProviderType(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'provider_type')
            && (str_contains($message, 'Data truncated') || str_contains($message, '1265'));
    }

    public function hostedCheckoutUrl(string $providerCode, SubscriptionCheckoutSession $session): string
    {
        return sprintf(
            'https://%s/platform/billing/providers/%s/checkout/%s',
            trim((string) config('saas.owner_host'), '.'),
            $providerCode,
            $session->session_token,
        );
    }

    /**
     * @return array<int, string>
     */
    private function subscriptionRelations(): array
    {
        return [
            'plan.limits',
            'plan.prices',
            'plan.features',
            'tenant',
            'invoices',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    private function normalizePrices(array $payload): array
    {
        $monthly = (float) Arr::get($payload, 'prices.monthly', Arr::get($payload, 'monthly_price', 0));
        $semiannual = (float) Arr::get($payload, 'prices.semiannual', round($monthly * 6, 2));
        $yearly = (float) Arr::get($payload, 'prices.yearly', Arr::get($payload, 'yearly_price', round($monthly * 12, 2)));

        return [
            'monthly' => round($monthly, 2),
            'semiannual' => round($semiannual, 2),
            'yearly' => round($yearly, 2),
        ];
    }

    private function planPriceAmount(PlatformPlan $plan, string $billingInterval): float
    {
        $plan->loadMissing('prices');

        $intervalPrice = $plan->prices->firstWhere('billing_interval', $billingInterval);

        if ($intervalPrice !== null) {
            return round((float) $intervalPrice->price_amount, 2);
        }

        return match ($billingInterval) {
            'yearly' => round((float) $plan->yearly_price, 2),
            'semiannual' => round((float) $plan->monthly_price * 6, 2),
            default => round((float) $plan->monthly_price, 2),
        };
    }

    private function determinePeriodEnd(string $billingInterval)
    {
        $now = now();

        return match ($billingInterval) {
            'yearly' => $now->copy()->addYear(),
            'semiannual' => $now->copy()->addMonths(6),
            default => $now->copy()->addMonth(),
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function activatePlanImmediately(
        Tenant $tenant,
        PlatformPlan $plan,
        string $billingInterval,
        ?User $actor = null,
        array $metadata = [],
    ): TenantSubscription {
        return DB::transaction(function () use ($tenant, $plan, $billingInterval, $actor, $metadata): TenantSubscription {
            $this->cancelPendingWorkflows($tenant);
            $this->cancelOperationalSubscriptions($tenant);

            $now = now();
            $trialEndsAt = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;

            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_code_snapshot' => $plan->code,
                'plan_name_snapshot' => $plan->name,
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'billing_interval' => $billingInterval,
                'currency_code' => $plan->currency_code,
                'price_amount' => $this->planPriceAmount($plan, $billingInterval),
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $now,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $this->determinePeriodEnd($billingInterval),
                'grace_ends_at' => null,
                'activated_by_user_id' => $actor?->id,
                'metadata_json' => $metadata !== [] ? $metadata : null,
            ]);

            $tenant->forceFill([
                'plan_code' => $plan->code,
            ])->save();

            $this->createInvoice($subscription, $plan);
            $this->syncTenantFeatureFlags($tenant, $plan, $actor);

            return $subscription->fresh($this->subscriptionRelations());
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createPendingActivation(
        Tenant $tenant,
        PlatformPlan $plan,
        string $billingInterval,
        ?User $actor = null,
        array $metadata = [],
    ): TenantSubscription {
        return DB::transaction(function () use ($tenant, $plan, $billingInterval, $actor, $metadata): TenantSubscription {
            $this->cancelPendingWorkflows($tenant);

            $now = now();
            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_code_snapshot' => $plan->code,
                'plan_name_snapshot' => $plan->name,
                'status' => 'pending_activation',
                'billing_interval' => $billingInterval,
                'currency_code' => $plan->currency_code,
                'price_amount' => $this->planPriceAmount($plan, $billingInterval),
                'trial_ends_at' => null,
                'starts_at' => null,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $this->determinePeriodEnd($billingInterval),
                'grace_ends_at' => null,
                'activated_by_user_id' => $actor?->id,
                'metadata_json' => $metadata !== [] ? $metadata : null,
            ]);

            $this->createInvoice($subscription, $plan, 'open');

            return $subscription->fresh($this->subscriptionRelations());
        });
    }

    private function activatePendingSubscription(TenantSubscription $subscription): TenantSubscription
    {
        return DB::transaction(function () use ($subscription): TenantSubscription {
            $subscription->loadMissing(['tenant', 'plan.limits', 'plan.prices', 'plan.features', 'invoices']);

            if ($subscription->status !== 'pending_activation') {
                return $subscription->fresh($this->subscriptionRelations());
            }

            $tenant = $subscription->tenant;
            $plan = $subscription->plan;

            if ($tenant === null || $plan === null) {
                return $subscription->fresh($this->subscriptionRelations());
            }

            $this->cancelOperationalSubscriptions($tenant, $subscription->id);

            $now = now();
            $trialEndsAt = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;

            $subscription->forceFill([
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $now,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $this->determinePeriodEnd($subscription->billing_interval),
                'grace_ends_at' => null,
                'ended_at' => null,
            ])->save();

            $tenant->forceFill([
                'plan_code' => $plan->code,
            ])->save();

            $this->syncTenantFeatureFlags($tenant, $plan);

            return $subscription->fresh($this->subscriptionRelations());
        });
    }

    private function applyGraceFallback(TenantSubscription $subscription): void
    {
        $tenant = $subscription->tenant ?? Tenant::query()->find($subscription->tenant_id);

        if ($tenant === null) {
            return;
        }

        $subscription->forceFill([
            'status' => 'expired',
            'ended_at' => now(),
        ])->save();

        $this->activatePlanImmediately(
            $tenant,
            $this->fallbackPlan(),
            'monthly',
            null,
            ['source' => 'grace_fallback', 'replaced_subscription_id' => $subscription->id]
        );
    }

    private function cancelPendingWorkflows(Tenant $tenant): void
    {
        $pendingSubscriptions = TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending_activation')
            ->pluck('id');

        if ($pendingSubscriptions->isNotEmpty()) {
            SubscriptionCheckoutSession::query()
                ->whereIn('tenant_subscription_id', $pendingSubscriptions)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            TenantSubscriptionInvoice::query()
                ->whereIn('tenant_subscription_id', $pendingSubscriptions)
                ->where('status', 'open')
                ->update(['status' => 'void']);

            TenantSubscription::query()
                ->whereIn('id', $pendingSubscriptions)
                ->update([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                ]);
        }
    }

    private function cancelOperationalSubscriptions(Tenant $tenant, ?int $exceptId = null): void
    {
        TenantSubscription::query()
            ->where('tenant_id', $tenant->id)
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->update([
                'status' => 'cancelled',
                'ended_at' => now(),
                'cancel_at_period_end' => false,
            ]);
    }

    private function fallbackPlan(): PlatformPlan
    {
        return PlatformPlan::query()
            ->with(['limits', 'prices', 'features'])
            ->where('code', self::FALLBACK_PLAN_CODE)
            ->firstOrFail();
    }

    private function effectivePlanForTenant(Tenant $tenant, ?TenantSubscription $subscription = null): ?PlatformPlan
    {
        $subscription = $subscription ?? $this->currentSubscription($tenant, false);

        if ($subscription !== null) {
            $subscription->loadMissing(['plan.limits', 'plan.prices', 'plan.features']);

            if ($subscription->status === 'past_due') {
                return $this->fallbackPlan();
            }

            if ($subscription->plan !== null) {
                return $subscription->plan;
            }
        }

        $planCode = $tenant->plan_code ?: self::FALLBACK_PLAN_CODE;

        return PlatformPlan::query()
            ->with(['limits', 'prices', 'features'])
            ->where('code', $planCode)
            ->first();
    }

    private function syncActiveTenantFeatureFlagsForPlan(PlatformPlan $plan): void
    {
        $subscriptions = TenantSubscription::query()
            ->with('tenant')
            ->where('plan_id', $plan->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $subscriptions->each(function (TenantSubscription $subscription) use ($plan): void {
            if ($subscription->tenant === null) {
                return;
            }

            $effectivePlan = $subscription->status === 'past_due' ? $this->fallbackPlan() : $plan;

            $this->syncTenantFeatureFlags($subscription->tenant, $effectivePlan);
        });
    }

    private function syncTenantFeatureFlags(Tenant $tenant, ?PlatformPlan $plan = null, ?User $actor = null): void
    {
        $effectivePlan = $plan ?? $this->effectivePlanForTenant($tenant);

        if ($effectivePlan === null) {
            return;
        }

        $effectivePlan->loadMissing('features');
        $booleanFeatures = $effectivePlan->features
            ->filter(fn (PlatformPlanFeature $feature) => $feature->feature_type === 'boolean')
            ->values();

        $existingFlags = TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('feature_code', $booleanFeatures->pluck('feature_code')->all())
            ->get()
            ->keyBy('feature_code');

        foreach ($booleanFeatures as $feature) {
            $existing = $existingFlags->get($feature->feature_code);

            if ($existing !== null && $existing->source === 'owner_override') {
                continue;
            }

            TenantFeatureFlag::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'feature_code' => $feature->feature_code,
                ],
                [
                    'status' => $this->booleanFeatureValue($feature->feature_value),
                    'source' => 'plan',
                    'updated_by_user_id' => $actor?->id,
                ]
            );
        }

        TenantFeatureFlag::query()
            ->where('tenant_id', $tenant->id)
            ->where('source', 'plan')
            ->whereNotIn('feature_code', $booleanFeatures->pluck('feature_code')->all())
            ->delete();
    }

    /**
     * @param  array<string, float>  $prices
     */
    private function syncPlanPrices(PlatformPlan $plan, array $prices): void
    {
        foreach ($prices as $interval => $amount) {
            PlatformPlanPrice::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'billing_interval' => $interval,
                ],
                [
                    'price_amount' => $amount,
                ]
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $limits
     */
    private function syncPlanLimits(PlatformPlan $plan, array $limits): void
    {
        $keys = [];

        foreach ($limits as $limit) {
            $key = (string) Arr::get($limit, 'key');

            if ($key === '') {
                continue;
            }

            $keys[] = $key;

            PlatformPlanLimit::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'limit_key' => $key,
                ],
                [
                    'limit_value' => Arr::get($limit, 'value'),
                    'is_unlimited' => (bool) Arr::get($limit, 'is_unlimited', false),
                ]
            );
        }

        if ($keys === []) {
            PlatformPlanLimit::query()
                ->where('plan_id', $plan->id)
                ->delete();

            return;
        }

        if ($keys !== []) {
            PlatformPlanLimit::query()
                ->where('plan_id', $plan->id)
                ->whereNotIn('limit_key', $keys)
                ->delete();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     */
    private function syncPlanFeatures(PlatformPlan $plan, array $features): void
    {
        $codes = [];

        foreach ($features as $index => $feature) {
            $code = (string) Arr::get($feature, 'code');

            if ($code === '') {
                continue;
            }

            $codes[] = $code;

            PlatformPlanFeature::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'feature_code' => $code,
                ],
                [
                    'display_label' => (string) Arr::get($feature, 'label', Str::headline(str_replace('_', ' ', $code))),
                    'compare_group' => (string) Arr::get($feature, 'group', 'Operations'),
                    'feature_type' => (string) Arr::get($feature, 'type', 'boolean'),
                    'feature_value' => $this->normalizeFeatureValue((string) Arr::get($feature, 'type', 'boolean'), Arr::get($feature, 'value')),
                    'sort_order' => (int) Arr::get($feature, 'sort_order', $index + 1),
                ]
            );
        }

        if ($codes === []) {
            PlatformPlanFeature::query()
                ->where('plan_id', $plan->id)
                ->delete();

            return;
        }

        if ($codes !== []) {
            PlatformPlanFeature::query()
                ->where('plan_id', $plan->id)
                ->whereNotIn('feature_code', $codes)
                ->delete();
        }
    }

    private function createInvoice(TenantSubscription $subscription, PlatformPlan $plan, ?string $status = null): TenantSubscriptionInvoice
    {
        $price = round((float) $subscription->price_amount, 2);
        $transactionFee = match ($plan->transaction_fee_type) {
            'fixed' => round((float) ($plan->transaction_fee_value ?? 0), 2),
            'percent' => round($price * ((float) ($plan->transaction_fee_value ?? 0) / 100), 2),
            default => 0,
        };
        $total = round($price + $transactionFee, 2);
        $resolvedStatus = $status ?? ($total <= 0 ? 'paid' : 'open');

        return TenantSubscriptionInvoice::query()->create([
            'tenant_subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'invoice_no' => 'INV-'.Str::upper(Str::random(10)),
            'status' => $resolvedStatus,
            'currency_code' => $subscription->currency_code,
            'subtotal_amount' => $price,
            'transaction_fee_amount' => $transactionFee,
            'total_amount' => $total,
            'period_starts_at' => $subscription->current_period_starts_at,
            'period_ends_at' => $subscription->current_period_ends_at,
            'issued_at' => now(),
            'due_at' => $resolvedStatus === 'paid' ? now() : now()->addDays(7),
            'paid_at' => $resolvedStatus === 'paid' ? now() : null,
            'metadata_json' => [
                'plan_code' => $subscription->plan_code_snapshot,
                'billing_interval' => $subscription->billing_interval,
                'invoice_type' => $subscription->status === 'pending_activation' ? 'activation' : 'renewal',
            ],
        ]);
    }

    private function serializeFeatureRow(PlatformPlanFeature $feature): array
    {
        $normalizedValue = $this->normalizeFeatureValue($feature->feature_type, $feature->feature_value);
        $enabled = $feature->feature_type === 'boolean'
            ? $this->booleanFeatureValue($normalizedValue)
            : filled($normalizedValue);

        return [
            'code' => $feature->feature_code,
            'label' => $feature->display_label,
            'group' => $feature->compare_group,
            'type' => $feature->feature_type,
            'value' => $normalizedValue,
            'display_value' => $this->displayFeatureValue($feature->feature_type, $normalizedValue),
            'enabled' => $enabled,
            'sort_order' => $feature->sort_order,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $features
     * @param  Collection<int, array<string, mixed>>  $limits
     * @return array<int, array<string, mixed>>
     */
    private function groupFeatureRows(Collection $features, Collection $limits): array
    {
        $limitRows = $limits->map(function (array $limit) {
            $group = match ($limit['key']) {
                'custom_domains' => 'Store & Branding',
                default => 'Operations',
            };

            $label = match ($limit['key']) {
                'products' => 'Product limit',
                'custom_domains' => 'Custom domains',
                'staff_members' => 'Staff members',
                default => Str::headline(str_replace('_', ' ', $limit['key'])),
            };

            return [
                'code' => 'limit_'.$limit['key'],
                'label' => $label,
                'group' => $group,
                'type' => 'text',
                'value' => $limit['display_value'],
                'display_value' => $limit['display_value'],
                'enabled' => true,
                'sort_order' => 1000,
            ];
        });

        return $features
            ->concat($limitRows)
            ->groupBy('group')
            ->map(fn (Collection $rows, string $group) => [
                'group' => $group,
                'items' => $rows->sortBy('sort_order')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeFeatureValue(string $type, $value): ?string
    {
        if ($value === null || $value === '') {
            return $type === 'boolean' ? '0' : null;
        }

        if ($type === 'boolean') {
            return $this->booleanFeatureValue($value) ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @param  mixed  $value
     */
    private function booleanFeatureValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(Str::lower((string) $value), ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    private function displayFeatureValue(string $type, ?string $value): string
    {
        return match ($type) {
            'boolean' => $this->booleanFeatureValue($value) ? 'Yes' : 'No',
            'percent' => filled($value) ? rtrim(rtrim((string) $value, '0'), '.').'%' : '-',
            default => filled($value) ? (string) $value : '-',
        };
    }

    /**
     * @param  array<string, string>  $query
     */
    private function merchantBillingUrl(array $query = []): string
    {
        $base = sprintf('https://%s/admin/settings/billing', trim((string) config('saas.merchant_host'), '.'));

        if ($query === []) {
            return $base;
        }

        return $base.'?'.http_build_query($query);
    }
}
