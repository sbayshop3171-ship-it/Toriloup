<?php

namespace App\Services\Saas;

use App\Models\PlatformPlan;
use App\Models\PlatformPlanLimit;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use App\Models\TenantSubscriptionInvoice;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionManagerService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function defaultPlans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'description' => 'Launch a small managed storefront with owner-controlled defaults.',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'trial_days' => 0,
                'transaction_fee_type' => 'percent',
                'transaction_fee_value' => 2.5,
                'metadata_json' => ['tier' => 'starter', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => 50, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 1, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 3, 'is_unlimited' => false],
                ],
            ],
            'growth' => [
                'name' => 'Growth',
                'description' => 'For growing merchants that need more catalog depth and team access.',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 49,
                'yearly_price' => 490,
                'trial_days' => 7,
                'transaction_fee_type' => 'percent',
                'transaction_fee_value' => 1.5,
                'metadata_json' => ['tier' => 'growth', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => 500, 'is_unlimited' => false],
                    ['key' => 'custom_domains', 'value' => 3, 'is_unlimited' => false],
                    ['key' => 'staff_members', 'value' => 15, 'is_unlimited' => false],
                ],
            ],
            'scale' => [
                'name' => 'Scale',
                'description' => 'High-capacity managed commerce for larger brands.',
                'status' => 'active',
                'currency_code' => 'USD',
                'monthly_price' => 199,
                'yearly_price' => 1990,
                'trial_days' => 14,
                'transaction_fee_type' => 'none',
                'transaction_fee_value' => 0,
                'metadata_json' => ['tier' => 'scale', 'managed' => true],
                'limits' => [
                    ['key' => 'products', 'value' => null, 'is_unlimited' => true],
                    ['key' => 'custom_domains', 'value' => null, 'is_unlimited' => true],
                    ['key' => 'staff_members', 'value' => null, 'is_unlimited' => true],
                ],
            ],
        ];
    }

    public function ensureDefaultPlans(): void
    {
        foreach ($this->defaultPlans() as $code => $payload) {
            $this->upsertPlan($code, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertPlan(string $code, array $payload): PlatformPlan
    {
        return DB::transaction(function () use ($code, $payload): PlatformPlan {
            $plan = PlatformPlan::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $payload['name'],
                    'description' => $payload['description'] ?? null,
                    'status' => $payload['status'] ?? 'draft',
                    'currency_code' => $payload['currency_code'] ?? 'USD',
                    'monthly_price' => $payload['monthly_price'] ?? 0,
                    'yearly_price' => $payload['yearly_price'] ?? 0,
                    'trial_days' => $payload['trial_days'] ?? 0,
                    'transaction_fee_type' => $payload['transaction_fee_type'] ?? 'none',
                    'transaction_fee_value' => $payload['transaction_fee_value'] ?? null,
                    'metadata_json' => $payload['metadata_json'] ?? null,
                ]
            );

            if (array_key_exists('limits', $payload) && is_array($payload['limits'])) {
                $this->syncPlanLimits($plan, $payload['limits']);
            }

            return $plan->fresh('limits');
        });
    }

    public function currentSubscription(Tenant $tenant, bool $provisionIfMissing = true): ?TenantSubscription
    {
        $this->ensureDefaultPlans();

        $subscription = TenantSubscription::query()
            ->with(['plan.limits', 'invoices' => fn ($query) => $query->latest('id')])
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trialing', 'active', 'past_due'])
            ->orderByDesc('id')
            ->first();

        if ($subscription === null && $provisionIfMissing) {
            return $this->assignPlanToTenant($tenant, $tenant->plan_code ?: 'starter');
        }

        return $subscription;
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

        $plan = PlatformPlan::query()->with('limits')->where('code', $planCode)->first();

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

        return DB::transaction(function () use ($tenant, $plan, $billingInterval, $actor, $metadata): TenantSubscription {
            TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('status', ['trialing', 'active', 'past_due'])
                ->update([
                    'status' => 'cancelled',
                    'ended_at' => now(),
                    'cancel_at_period_end' => false,
                ]);

            $now = now();
            $periodEndsAt = $billingInterval === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth();
            $trialEndsAt = $plan->trial_days > 0 ? $now->copy()->addDays($plan->trial_days) : null;
            $price = $billingInterval === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;

            $subscription = TenantSubscription::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_code_snapshot' => $plan->code,
                'plan_name_snapshot' => $plan->name,
                'status' => $trialEndsAt ? 'trialing' : 'active',
                'billing_interval' => $billingInterval,
                'currency_code' => $plan->currency_code,
                'price_amount' => $price,
                'trial_ends_at' => $trialEndsAt,
                'starts_at' => $now,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $periodEndsAt,
                'activated_by_user_id' => $actor?->id,
                'metadata_json' => $metadata !== [] ? $metadata : null,
            ]);

            $tenant->forceFill([
                'plan_code' => $plan->code,
            ])->save();

            $this->createInvoice($subscription, $plan);

            return $subscription->fresh(['plan.limits', 'invoices' => fn ($query) => $query->latest('id')]);
        });
    }

    public function usageSummary(Tenant $tenant): array
    {
        $subscription = $this->currentSubscription($tenant);
        $limits = $subscription?->plan?->limits->keyBy('limit_key') ?? collect();

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

    public function markInvoicePaid(TenantSubscriptionInvoice $invoice): TenantSubscriptionInvoice
    {
        $invoice->forceFill([
            'status' => 'paid',
            'paid_at' => now(),
        ])->save();

        $subscription = $invoice->subscription;

        if ($subscription !== null && $subscription->status === 'past_due') {
            $subscription->forceFill([
                'status' => 'active',
            ])->save();
        }

        return $invoice->fresh(['subscription.plan.limits']);
    }

    public function serializeSubscription(TenantSubscription $subscription): array
    {
        $subscription->loadMissing(['plan.limits', 'tenant', 'invoices' => fn ($query) => $query->latest('id')]);

        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'status' => $subscription->status,
            'billing_interval' => $subscription->billing_interval,
            'currency_code' => $subscription->currency_code,
            'price_amount' => $subscription->price_amount,
            'trial_ends_at' => $subscription->trial_ends_at,
            'starts_at' => $subscription->starts_at,
            'current_period_starts_at' => $subscription->current_period_starts_at,
            'current_period_ends_at' => $subscription->current_period_ends_at,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'ended_at' => $subscription->ended_at,
            'tenant' => $subscription->tenant?->only(['id', 'name', 'slug', 'status', 'plan_code']),
            'plan' => [
                'id' => $subscription->plan?->id,
                'code' => $subscription->plan?->code ?? $subscription->plan_code_snapshot,
                'name' => $subscription->plan?->name ?? $subscription->plan_name_snapshot,
                'currency_code' => $subscription->plan?->currency_code ?? $subscription->currency_code,
                'monthly_price' => $subscription->plan?->monthly_price,
                'yearly_price' => $subscription->plan?->yearly_price,
                'trial_days' => $subscription->plan?->trial_days,
                'transaction_fee_type' => $subscription->plan?->transaction_fee_type,
                'transaction_fee_value' => $subscription->plan?->transaction_fee_value,
                'limits' => $subscription->plan?->limits->map(fn (PlatformPlanLimit $limit) => [
                    'key' => $limit->limit_key,
                    'value' => $limit->limit_value,
                    'is_unlimited' => $limit->is_unlimited,
                ])->values() ?? [],
            ],
            'invoices' => $subscription->invoices->map(fn (TenantSubscriptionInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'status' => $invoice->status,
                'currency_code' => $invoice->currency_code,
                'subtotal_amount' => $invoice->subtotal_amount,
                'transaction_fee_amount' => $invoice->transaction_fee_amount,
                'total_amount' => $invoice->total_amount,
                'issued_at' => $invoice->issued_at,
                'due_at' => $invoice->due_at,
                'paid_at' => $invoice->paid_at,
                'period_starts_at' => $invoice->period_starts_at,
                'period_ends_at' => $invoice->period_ends_at,
            ])->values(),
        ];
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

        if ($keys !== []) {
            PlatformPlanLimit::query()
                ->where('plan_id', $plan->id)
                ->whereNotIn('limit_key', $keys)
                ->delete();
        }
    }

    private function createInvoice(TenantSubscription $subscription, PlatformPlan $plan): TenantSubscriptionInvoice
    {
        $price = (float) $subscription->price_amount;
        $transactionFee = match ($plan->transaction_fee_type) {
            'fixed' => (float) ($plan->transaction_fee_value ?? 0),
            'percent' => round($price * ((float) ($plan->transaction_fee_value ?? 0) / 100), 2),
            default => 0,
        };
        $total = $price + $transactionFee;
        $status = $total <= 0 ? 'paid' : 'open';

        return TenantSubscriptionInvoice::query()->create([
            'tenant_subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'invoice_no' => 'INV-'.Str::upper(Str::random(10)),
            'status' => $status,
            'currency_code' => $subscription->currency_code,
            'subtotal_amount' => $price,
            'transaction_fee_amount' => $transactionFee,
            'total_amount' => $total,
            'period_starts_at' => $subscription->current_period_starts_at,
            'period_ends_at' => $subscription->current_period_ends_at,
            'issued_at' => now(),
            'due_at' => $status === 'paid' ? now() : now()->addDays(7),
            'paid_at' => $status === 'paid' ? now() : null,
            'metadata_json' => [
                'plan_code' => $subscription->plan_code_snapshot,
                'billing_interval' => $subscription->billing_interval,
            ],
        ]);
    }
}
