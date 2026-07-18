<?php

namespace Tests\Feature;

use App\Enums\Ask;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Status;
use App\Models\MerchantPayoutMethod;
use App\Models\MerchantWallet;
use App\Models\MerchantWalletTransaction;
use App\Models\MerchantWithdrawal;
use App\Models\Order;
use App\Models\PlatformPlan;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\Saas\MerchantWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantWalletSettlementTest extends TestCase
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
            'saas.wallet.holding_days' => 0,
            'saas.wallet.min_withdrawal_amount' => 0,
        ]);
    }

    public function test_successful_online_payment_credits_wallet_with_plan_fee_once(): void
    {
        $tenant = $this->createTenant('wallet-credit-store');
        $this->subscribeTenant($tenant, 'percent', 10);
        $customer = $this->createUser('wallet-credit-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $order = $this->createOrder($tenant, $customer, 100);

        app(PaymentService::class)->payment($order, 'stripe', 'txn-wallet-credit-1');
        app(PaymentService::class)->payment($order->fresh(), 'stripe', 'txn-wallet-credit-1');

        $wallet = MerchantWallet::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertEquals(90.0, (float) $wallet->available_balance);
        $this->assertEquals(0.0, (float) $wallet->holding_balance);
        $this->assertEquals(90.0, (float) $wallet->total_earned);
        $this->assertEquals(10.0, (float) $wallet->total_fees);
        $this->assertSame(1, MerchantWalletTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)->count());

        $codOrder = $this->createOrder($tenant, $customer, 75);
        app(PaymentService::class)->payment($codOrder, 'cashondelivery', 'txn-cod-skip');

        $this->assertSame(1, MerchantWalletTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)->count());
    }

    public function test_holding_period_releases_payment_into_available_balance(): void
    {
        config(['saas.wallet.holding_days' => 2]);

        $tenant = $this->createTenant('wallet-hold-store');
        $this->subscribeTenant($tenant);
        $customer = $this->createUser('wallet-hold-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $order = $this->createOrder($tenant, $customer, 80);

        app(PaymentService::class)->payment($order, 'paypal', 'txn-wallet-hold-1');

        $wallet = MerchantWallet::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $wallet->available_balance);
        $this->assertEquals(80.0, (float) $wallet->holding_balance);

        MerchantWalletTransaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->update(['available_at' => now()->subMinute()]);

        app(MerchantWalletService::class)->summary($tenant);

        $wallet->refresh();
        $this->assertEquals(80.0, (float) $wallet->available_balance);
        $this->assertEquals(0.0, (float) $wallet->holding_balance);
    }

    public function test_merchant_can_request_and_owner_can_approve_withdrawal(): void
    {
        $owner = $this->createPlatformOwner();
        $context = $this->createMerchantContext('wallet-approval-store');
        $tenant = $context['tenant'];
        $customer = $this->createUser('wallet-approval-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $order = $this->createOrder($tenant, $customer, 120);
        app(PaymentService::class)->payment($order, 'stripe', 'txn-wallet-approval-1');

        $platformToken = $this->platformToken($owner);
        $merchantToken = $this->merchantToken($context['user']);

        $methodResponse = $this
            ->withToken($platformToken)
            ->withHeaders($this->jsonHeaders())
            ->postJson('http://owner.company.com/api/platform/wallet/payout-methods', [
                'code' => 'bkash',
                'name' => 'bKash',
                'status' => true,
                'min_amount' => 10,
                'fee_type' => 'fixed',
                'fee_value' => 5,
                'fields' => [
                    ['key' => 'account_number', 'label' => 'bKash Number', 'type' => 'text', 'required' => true, 'width' => 50],
                    ['key' => 'account_type', 'label' => 'Account Type', 'type' => 'select', 'required' => true, 'width' => 50, 'options' => ['Personal', 'Agent']],
                ],
            ]);

        $methodResponse
            ->assertOk()
            ->assertJsonPath('data.code', 'bkash')
            ->assertJsonPath('data.fields.0.label', 'bKash Number')
            ->assertJsonPath('data.fields.1.options.0', 'Personal');

        $payoutMethodId = $methodResponse->json('data.id');

        $this
            ->withToken($merchantToken)
            ->withHeaders($this->jsonHeaders($tenant))
            ->getJson('http://merchant.company.com/api/merchant/wallet/summary')
            ->assertOk()
            ->assertJsonPath('data.wallet.available_balance', 120);

        $this
            ->withToken($merchantToken)
            ->withHeaders($this->jsonHeaders($tenant))
            ->postJson('http://merchant.company.com/api/merchant/wallet/withdrawals', [
                'payout_method_id' => $payoutMethodId,
                'amount' => 50,
                'destination' => [
                    'account_number' => '01700000000',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Account Type is required for bKash withdrawal.');

        $withdrawalResponse = $this
            ->withToken($merchantToken)
            ->withHeaders($this->jsonHeaders($tenant))
            ->postJson('http://merchant.company.com/api/merchant/wallet/withdrawals', [
                'payout_method_id' => $payoutMethodId,
                'amount' => 50,
                'destination' => [
                    'account_number' => '01700000000',
                    'account_type' => 'Personal',
                ],
                'merchant_note' => 'Please send today.',
            ]);

        $withdrawalResponse
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', 50)
            ->assertJsonPath('data.fee_amount', 5)
            ->assertJsonPath('data.destination_details.0.label', 'bKash Number')
            ->assertJsonPath('data.destination_details.0.value', '01700000000')
            ->assertJsonPath('data.destination_details.1.value', 'Personal');

        $wallet = MerchantWallet::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEquals(65.0, (float) $wallet->available_balance);
        $this->assertEquals(50.0, (float) $wallet->pending_withdrawal_balance);

        $withdrawalId = $withdrawalResponse->json('data.id');

        $this
            ->withToken($platformToken)
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://owner.company.com/api/platform/wallet/withdrawals?status=pending')
            ->assertOk()
            ->assertJsonPath('data.0.id', $withdrawalId)
            ->assertJsonPath('data.0.destination_details.0.label', 'bKash Number')
            ->assertJsonPath('data.0.destination_details.1.label', 'Account Type')
            ->assertJsonPath('data.0.requested_by.email', $context['user']->email);

        $this
            ->withToken($platformToken)
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://owner.company.com/api/platform/wallet/withdrawals/{$withdrawalId}/approve", [
                'payout_reference' => 'BKASH-TXN-1',
                'admin_note' => 'Paid by owner.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.payout_reference', 'BKASH-TXN-1');

        $wallet->refresh();
        $this->assertEquals(65.0, (float) $wallet->available_balance);
        $this->assertEquals(0.0, (float) $wallet->pending_withdrawal_balance);
        $this->assertEquals(50.0, (float) $wallet->total_withdrawn);

        $this
            ->withToken($platformToken)
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://owner.company.com/api/platform/wallet/reports/statement')
            ->assertOk()
            ->assertJsonPath('data.totals.withdrawal_requests', 50);

        $this
            ->withToken($platformToken)
            ->withHeaders($this->jsonHeaders())
            ->get('http://owner.company.com/api/platform/wallet/reports/export?type=withdrawals')
            ->assertOk();
    }

    public function test_rejected_withdrawal_restores_wallet_and_refund_adjusts_balance_once(): void
    {
        $owner = $this->createPlatformOwner();
        $context = $this->createMerchantContext('wallet-reject-store');
        $tenant = $context['tenant'];
        $customer = $this->createUser('wallet-reject-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $order = $this->createOrder($tenant, $customer, 100);
        $paymentTransaction = app(PaymentService::class)->payment($order, 'stripe', 'txn-wallet-reject-1');

        $method = MerchantPayoutMethod::query()->create([
            'code' => 'bank',
            'name' => 'Bank Transfer',
            'status' => true,
            'min_amount' => 0,
            'fee_type' => 'fixed',
            'fee_value' => 2,
            'sort_order' => 1,
        ]);

        $withdrawal = app(MerchantWalletService::class)->requestWithdrawal($tenant, $context['user'], [
            'payout_method_id' => $method->id,
            'amount' => 40,
            'destination' => ['account_number' => '123456'],
        ]);

        $wallet = MerchantWallet::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEquals(58.0, (float) $wallet->available_balance);
        $this->assertEquals(40.0, (float) $wallet->pending_withdrawal_balance);

        app(MerchantWalletService::class)->rejectWithdrawal($withdrawal, $owner, 'Bank details are incorrect.');

        $wallet->refresh();
        $this->assertEquals(100.0, (float) $wallet->available_balance);
        $this->assertEquals(0.0, (float) $wallet->pending_withdrawal_balance);
        $this->assertEquals(0.0, (float) $wallet->total_fees);
        $this->assertSame('rejected', MerchantWithdrawal::withoutGlobalScopes()->findOrFail($withdrawal->id)->status);

        app(MerchantWalletService::class)->reverseOrderPayment($order->fresh(), 25, 'chargeback', 1);
        app(MerchantWalletService::class)->reverseOrderPayment($order->fresh(), 25, 'chargeback', 1);

        $wallet->refresh();
        $this->assertEquals(75.0, (float) $wallet->available_balance);
        $this->assertEquals(25.0, (float) $wallet->total_refunded);
        $this->assertSame(1, MerchantWalletTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', MerchantWalletService::TYPE_REFUND_ADJUSTMENT)->count());
        $this->assertSame($tenant->id, $paymentTransaction->fresh()->tenant_id);
    }

    private function createPlatformOwner(): User
    {
        return $this->createUser('owner@platform.test', LegacyRole::ADMIN, 'admin', [
            'name' => 'Platform Owner',
            'username' => 'platform-owner-' . Str::random(5),
        ]);
    }

    /**
     * @return array{user: User, tenant: Tenant}
     */
    private function createMerchantContext(string $slug): array
    {
        $user = $this->createUser($slug . '@merchant.test', LegacyRole::MANAGER, 'manager', [
            'name' => Str::headline($slug) . ' Merchant',
            'username' => $slug . '-merchant',
        ]);

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

        $this->subscribeTenant($tenant);

        return compact('user', 'tenant');
    }

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 4)) . strtoupper(Str::random(4)),
            'status' => 'active',
            'plan_code' => 'starter',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
            'contact_email' => $slug . '@store.test',
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

    private function subscribeTenant(Tenant $tenant, string $feeType = 'none', float $feeValue = 0): TenantSubscription
    {
        $plan = PlatformPlan::query()->create([
            'code' => 'plan-' . $tenant->slug . '-' . Str::random(5),
            'name' => 'Wallet Plan',
            'status' => 'active',
            'currency_code' => 'USD',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'trial_days' => 0,
            'transaction_fee_type' => $feeType,
            'transaction_fee_value' => $feeValue,
        ]);

        return TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'plan_code_snapshot' => $plan->code,
            'plan_name_snapshot' => $plan->name,
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency_code' => 'USD',
            'price_amount' => 0,
            'starts_at' => now(),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);
    }

    private function createOrder(Tenant $tenant, User $customer, float $total): Order
    {
        return Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'ORD-' . Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => $total,
            'order_datetime' => now(),
            'payment_method' => 2,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => Ask::NO,
        ]);
    }

    private function createUser(string $email, int $roleId, string $roleName, array $overrides = []): User
    {
        $role = Role::query()->find($roleId);

        if ($role === null) {
            $role = new Role();
            $role->id = $roleId;
            $role->name = $roleName;
            $role->guard_name = 'web';
            $role->save();
        }

        $user = User::factory()->create(array_merge([
            'name' => Str::headline($roleName),
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => Status::ACTIVE,
            'username' => Str::slug($roleName) . '-' . Str::random(8),
            'country_code' => '+880',
            'is_guest' => 0,
        ], $overrides));

        $user->assignRole($role);

        return $user;
    }

    private function platformToken(User $user): string
    {
        $response = $this
            ->withHeaders($this->jsonHeaders())
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
            ->withHeaders($this->jsonHeaders())
            ->postJson('http://merchant.company.com/api/merchant/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertCreated()->assertJsonPath('surface', 'merchant');

        return (string) $response->json('token');
    }

    private function jsonHeaders(?Tenant $tenant = null): array
    {
        $headers = [
            'x-api-key' => 'testing-key',
            'x-localization' => 'en',
        ];

        if ($tenant instanceof Tenant) {
            $headers['X-Tenant-Slug'] = $tenant->slug;
        }

        return $headers;
    }
}
