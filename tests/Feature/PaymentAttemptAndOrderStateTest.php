<?php

namespace Tests\Feature;

use App\Enums\Ask;
use App\Enums\OrderStatus;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Status;
use App\Models\MerchantWalletTransaction;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentAttemptService;
use App\Services\PaymentService;
use App\Services\Saas\MerchantWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentAttemptAndOrderStateTest extends TestCase
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

    public function test_payment_attempts_are_recorded_and_success_callbacks_are_idempotent(): void
    {
        $tenant = $this->createTenant('attempt-store');
        $customer = $this->createUser('attempt-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $gateway = $this->createGateway('Stripe', 'stripe');
        $this->createTenantPaymentMethod($tenant, 'stripe');
        $order = $this->createOrder($tenant, $customer, $gateway, 100);

        $attempt = app(PaymentAttemptService::class)->prepare($order, 'stripe', 'idem-attempt-1');

        $this->assertSame(PaymentAttemptStatus::PENDING, $attempt->status);
        $this->assertSame($tenant->id, $attempt->tenant_id);
        $this->assertSame($gateway->id, $attempt->payment_gateway_id);

        app(PaymentService::class)->payment($order, 'stripe', 'txn-attempt-1');
        app(PaymentService::class)->payment($order->fresh(), 'stripe', 'txn-attempt-1');

        $attempt->refresh();
        $order->refresh();

        $this->assertSame(PaymentAttemptStatus::SUCCEEDED, $attempt->status);
        $this->assertSame('txn-attempt-1', $attempt->provider_transaction_id);
        $this->assertTrue($attempt->backend_validation_passed);
        $this->assertSame(PaymentStatus::PAID, (int) $order->payment_status);
        $this->assertSame(Ask::YES, (int) $order->active);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('order_id', $order->id)->where('type', 'payment')->count());
        $this->assertSame(1, PaymentAttempt::withoutGlobalScopes()->where('order_id', $order->id)->count());
        $this->assertSame(1, MerchantWalletTransaction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)->count());
    }

    public function test_owner_order_detail_is_read_only_and_includes_payment_attempt_timeline(): void
    {
        $owner = $this->createUser('owner-attempt@test.com', LegacyRole::ADMIN, 'admin');
        $tenant = $this->createTenant('owner-attempt-store');
        $customer = $this->createUser('owner-attempt-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $gateway = $this->createGateway('Stripe', 'stripe');
        $this->createTenantPaymentMethod($tenant, 'stripe');
        $order = $this->createOrder($tenant, $customer, $gateway, 75);

        app(PaymentAttemptService::class)->prepare($order, 'stripe', 'owner-detail-attempt');
        app(PaymentService::class)->payment($order, 'stripe', 'txn-owner-detail-1');

        Sanctum::actingAs($owner, ['surface:platform']);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://owner.company.com/api/platform/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.tenant.slug', 'owner-attempt-store')
            ->assertJsonPath('data.payment_attempts.0.status', PaymentAttemptStatus::SUCCEEDED)
            ->assertJsonPath('data.payment_attempts.0.provider_transaction_id', 'txn-owner-detail-1');

        $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://owner.company.com/api/platform/orders/{$order->id}/status", [
                'status' => OrderStatus::CONFIRMED,
            ])
            ->assertStatus(405);
    }

    public function test_merchant_order_status_state_machine_blocks_invalid_transitions(): void
    {
        $context = $this->createMerchantContext('state-machine-store');
        $customer = $this->createUser('state-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $gateway = $this->createGateway('Cash on Delivery', 'cashondelivery');
        $order = $this->createOrder($context['tenant'], $customer, $gateway, 45);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::DELIVERED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order status transition not allowed from Pending to Delivered.');

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::CONFIRMED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::CONFIRMED);

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::DELIVERED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order status transition not allowed from Confirmed to Delivered.');

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::ON_THE_WAY,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::ON_THE_WAY);

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::DELIVERED,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::DELIVERED);

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$order->id}/status", [
                'status' => OrderStatus::CANCELED,
                'reason' => 'Customer changed mind',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Order status transition not allowed from Delivered to Canceled.');
    }

    public function test_merchant_payment_status_blocks_unsafe_manual_changes(): void
    {
        $context = $this->createMerchantContext('payment-state-store');
        $customer = $this->createUser('payment-state-customer@test.com', LegacyRole::CUSTOMER, 'customer');
        $gateway = $this->createGateway('Cash on Delivery', 'cashondelivery');
        $canceledOrder = $this->createOrder($context['tenant'], $customer, $gateway, 55, [
            'status' => OrderStatus::CANCELED,
        ]);
        $paidOrder = $this->createOrder($context['tenant'], $customer, $gateway, 65, [
            'payment_status' => PaymentStatus::PAID,
            'status' => OrderStatus::CONFIRMED,
            'active' => Ask::YES,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$canceledOrder->id}/payment-status", [
                'payment_status' => PaymentStatus::PAID,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Canceled or rejected orders cannot be marked paid.');

        $this
            ->withHeaders($this->jsonHeaders($context['tenant']))
            ->postJson("http://merchant.company.com/api/merchant/orders/{$paidOrder->id}/payment-status", [
                'payment_status' => PaymentStatus::UNPAID,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Paid orders cannot be marked unpaid. Use refund or adjustment flow instead.');
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
            'contact_email' => "{$slug}@store.test",
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

    /**
     * @return array{user: User, tenant: Tenant}
     */
    private function createMerchantContext(string $slug): array
    {
        $merchant = $this->createUser("{$slug}@merchant.test", LegacyRole::MANAGER, 'manager');
        $tenant = $this->createTenant($slug);
        $platformRole = PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_owner'],
            ['name' => 'Merchant Owner', 'scope' => 'merchant', 'is_system' => true]
        );

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $merchant->id,
            'role_id' => $platformRole->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return ['user' => $merchant, 'tenant' => $tenant];
    }

    private function createUser(string $email, int $roleId, string $roleName): User
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
            'name' => Str::headline(Str::before($email, '@')),
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => Status::ACTIVE,
            'username' => Str::slug(Str::before($email, '@')).'-'.Str::random(5),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function createGateway(string $name, string $slug): PaymentGateway
    {
        return PaymentGateway::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => Status::ACTIVE,
        ]);
    }

    private function createTenantPaymentMethod(Tenant $tenant, string $providerCode): TenantPaymentMethod
    {
        return TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => $providerCode,
            'display_name' => Str::headline($providerCode),
            'status' => true,
            'checkout_label' => 'Pay with '.Str::headline($providerCode),
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);
    }

    private function createOrder(Tenant $tenant, User $customer, PaymentGateway $gateway, float $total, array $overrides = []): Order
    {
        return Order::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'ORD-'.Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => $total,
            'order_datetime' => now(),
            'payment_method' => $gateway->id,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => Ask::NO,
        ], $overrides));
    }
}
