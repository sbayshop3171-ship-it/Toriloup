<?php

namespace Tests\Feature;

use App\Enums\Ask;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Status;
use App\Models\Order;
use App\Models\NotificationAlert;
use App\Models\PushDeviceToken;
use App\Models\PaymentGateway;
use App\Models\PlatformRole;
use App\Models\ReturnAndRefund;
use App\Models\ReturnReason;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\FirebaseService;
use App\Services\MerchantNotificationPreferenceService;
use App\Services\PushDeviceTokenService;
use App\Services\TransactionalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionalNotificationTest extends TestCase
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

    public function test_new_order_notification_is_scoped_to_the_order_tenant_members(): void
    {
        $alphaTenant = $this->createTenant('alpha-notifications');
        $betaTenant = $this->createTenant('beta-notifications');
        $alphaMerchant = $this->createUser('alpha-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $betaMerchant = $this->createUser('beta-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $customer = $this->createUser('customer@example.test', LegacyRole::CUSTOMER, 'customer');

        $alphaMerchant->forceFill(['web_token' => 'alpha-web-token'])->save();
        $betaMerchant->forceFill(['web_token' => 'beta-web-token'])->save();

        $this->attachTenantMember($alphaTenant, $alphaMerchant);
        $this->attachTenantMember($betaTenant, $betaMerchant);

        $order = $this->createOrder($alphaTenant, $customer);

        $this->mock(FirebaseService::class, function ($mock): void {
            $mock->shouldReceive('sendNotification')
                ->once()
                ->with(
                    Mockery::on(fn ($payload): bool => $payload->event_key === 'admin_and_manager_new_order_message'),
                    ['alpha-web-token'],
                    'new-order-found'
                );
        });

        app(TransactionalNotificationService::class)->sendNewOrderToMerchant($order);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $alphaTenant->id,
            'user_id' => $alphaMerchant->id,
            'audience' => 'merchant',
            'event_key' => 'admin_and_manager_new_order_message',
            'target_type' => 'order',
            'target_value' => (string) $order->id,
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'tenant_id' => $alphaTenant->id,
            'user_id' => $betaMerchant->id,
            'event_key' => 'admin_and_manager_new_order_message',
        ]);
    }

    public function test_merchant_preferences_default_on_and_are_isolated_by_store(): void
    {
        $alphaTenant = $this->createTenant('alpha-preferences');
        $betaTenant = $this->createTenant('beta-preferences');
        $merchant = $this->createUser('preferences-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $this->attachTenantMember($alphaTenant, $merchant);
        $this->attachTenantMember($betaTenant, $merchant);

        $preferences = app(MerchantNotificationPreferenceService::class);
        $eventKey = 'admin_and_manager_new_order_message';

        $this->assertTrue($preferences->enabledFor($alphaTenant, $merchant, $eventKey));
        $this->assertTrue($preferences->enabledFor($betaTenant, $merchant, $eventKey));

        $preferences->update($alphaTenant, $merchant, $eventKey, false);

        $this->assertFalse($preferences->enabledFor($alphaTenant, $merchant, $eventKey));
        $this->assertTrue($preferences->enabledFor($betaTenant, $merchant, $eventKey));
    }

    public function test_notification_preference_api_is_scoped_to_the_current_store(): void
    {
        $alphaTenant = $this->createTenant('alpha-preference-api');
        $betaTenant = $this->createTenant('beta-preference-api');
        $merchant = $this->createUser('preference-api-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $this->attachTenantMember($alphaTenant, $merchant);
        $this->attachTenantMember($betaTenant, $merchant);
        Sanctum::actingAs($merchant, ['surface:merchant']);

        $this->withHeaders($this->tenantHeaders($alphaTenant->slug))
            ->getJson('http://merchant.company.com/api/admin/notification-preferences')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.enabled', true);

        $this->withHeaders($this->tenantHeaders($alphaTenant->slug))
            ->patchJson('http://merchant.company.com/api/admin/notification-preferences/admin_and_manager_new_order_message', [
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->withHeaders($this->tenantHeaders($betaTenant->slug))
            ->getJson('http://merchant.company.com/api/admin/notification-preferences')
            ->assertOk()
            ->assertJsonPath('data.0.enabled', true);
    }

    public function test_account_can_register_multiple_devices_and_remove_one_installation(): void
    {
        $user = $this->createUser('multi-device@example.test', LegacyRole::MANAGER, 'manager');
        $service = app(PushDeviceTokenService::class);
        $androidInstallation = (string) Str::uuid();
        $webInstallation = (string) Str::uuid();

        $service->register($user, $androidInstallation, 'android', 'android-token-one');
        $service->register($user, $webInstallation, 'web', 'web-token-two');

        $this->assertSame(2, PushDeviceToken::query()->where('user_id', $user->id)->count());
        $this->assertEqualsCanonicalizing(
            ['android-token-one', 'web-token-two'],
            $service->tokensFor($user)->all()
        );

        $this->assertTrue($service->unregister($user, $androidInstallation));
        $this->assertEquals(['web-token-two'], $service->tokensFor($user)->all());
    }

    public function test_owner_off_suppresses_new_merchant_inbox_rows_and_push(): void
    {
        $tenant = $this->createTenant('owner-switch-off');
        $merchant = $this->createUser('owner-switch-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $customer = $this->createUser('owner-switch-customer@example.test', LegacyRole::CUSTOMER, 'customer');
        $merchant->forceFill(['web_token' => 'owner-switch-web-token'])->save();
        $this->attachTenantMember($tenant, $merchant);
        $order = $this->createOrder($tenant, $customer);

        NotificationAlert::query()->updateOrCreate(
            ['language' => 'admin_and_manager_new_order_message'],
            [
                'name' => 'New merchant order',
                'mail_message' => 'New order',
                'sms_message' => 'New order',
                'push_notification_message' => 'New order',
                'mail' => 10,
                'sms' => 10,
                'push_notification' => 10,
            ]
        );

        $this->mock(FirebaseService::class, function ($mock): void {
            $mock->shouldNotReceive('sendNotification');
        });

        app(TransactionalNotificationService::class)->sendNewOrderToMerchant($order);

        $this->assertDatabaseMissing('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $merchant->id,
            'event_key' => 'admin_and_manager_new_order_message',
        ]);
    }

    public function test_merchant_off_suppresses_only_future_rows_and_keeps_old_history(): void
    {
        $tenant = $this->createTenant('merchant-switch-off');
        $disabledMerchant = $this->createUser('disabled-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $enabledMerchant = $this->createUser('enabled-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $customer = $this->createUser('merchant-switch-customer@example.test', LegacyRole::CUSTOMER, 'customer');
        $disabledMerchant->forceFill(['web_token' => 'disabled-merchant-web-token'])->save();
        $enabledMerchant->forceFill(['web_token' => 'enabled-merchant-web-token'])->save();
        $this->attachTenantMember($tenant, $disabledMerchant);
        $this->attachTenantMember($tenant, $enabledMerchant);
        $order = $this->createOrder($tenant, $customer);

        $oldHistory = UserNotification::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $disabledMerchant->id,
            'audience' => 'merchant',
            'event_key' => 'admin_and_manager_new_order_message',
            'title' => 'Old order alert',
            'body' => 'Existing notification history remains.',
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'data' => [],
        ]);
        app(MerchantNotificationPreferenceService::class)->update(
            $tenant,
            $disabledMerchant,
            'admin_and_manager_new_order_message',
            false
        );

        $this->mock(FirebaseService::class, function ($mock) use ($enabledMerchant): void {
            $mock->shouldReceive('sendNotification')
                ->once()
                ->with(
                    Mockery::on(fn ($payload): bool => $payload->audience === 'merchant'),
                    ['enabled-merchant-web-token'],
                    'new-order-found'
                );
        });

        app(TransactionalNotificationService::class)->sendNewOrderToMerchant($order);

        $this->assertDatabaseHas('user_notifications', ['id' => $oldHistory->id]);
        $this->assertSame(1, UserNotification::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $disabledMerchant->id)
            ->where('event_key', 'admin_and_manager_new_order_message')
            ->count());
        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $enabledMerchant->id,
            'event_key' => 'admin_and_manager_new_order_message',
        ]);
    }

    public function test_payment_and_return_notifications_create_customer_inbox_records(): void
    {
        $tenant = $this->createTenant('customer-notifications');
        $merchant = $this->createUser('merchant@example.test', LegacyRole::MANAGER, 'manager');
        $customer = $this->createUser('buyer@example.test', LegacyRole::CUSTOMER, 'customer');
        $this->attachTenantMember($tenant, $merchant);
        $order = $this->createOrder($tenant, $customer);
        $returnReason = ReturnReason::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Wrong size',
            'status' => Status::ACTIVE,
        ]);
        $return = ReturnAndRefund::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'return_reason_id' => $returnReason->id,
            'note' => 'Wrong size',
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'order_serial_no' => $order->order_serial_no,
            'status' => 10,
        ]);

        $this->mock(FirebaseService::class, function ($mock): void {
            $mock->shouldNotReceive('sendNotification');
        });

        $service = app(TransactionalNotificationService::class);
        $service->sendPaymentFailed($order);
        $service->sendReturnStatus($return, 10);

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'audience' => 'customer',
            'event_key' => 'payment_failed_message',
            'target_type' => 'order',
            'target_value' => (string) $order->id,
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
            'audience' => 'customer',
            'event_key' => 'return_approved_message',
            'target_type' => 'return_order',
            'target_value' => (string) $return->id,
        ]);
    }

    public function test_admin_notification_inbox_is_scoped_to_current_merchant_tenant(): void
    {
        $alphaTenant = $this->createTenant('alpha-inbox');
        $betaTenant = $this->createTenant('beta-inbox');
        $merchant = $this->createUser('multi-tenant-merchant@example.test', LegacyRole::MANAGER, 'manager');
        $this->attachTenantMember($alphaTenant, $merchant);
        $this->attachTenantMember($betaTenant, $merchant);

        $alphaNotification = UserNotification::query()->create([
            'tenant_id' => $alphaTenant->id,
            'user_id' => $merchant->id,
            'audience' => 'merchant',
            'event_key' => 'alpha_event',
            'title' => 'Alpha alert',
            'body' => 'Alpha tenant notification',
            'target_type' => 'order',
            'target_value' => '100',
            'data' => [],
        ]);
        $betaNotification = UserNotification::query()->create([
            'tenant_id' => $betaTenant->id,
            'user_id' => $merchant->id,
            'audience' => 'merchant',
            'event_key' => 'beta_event',
            'title' => 'Beta alert',
            'body' => 'Beta tenant notification',
            'target_type' => 'order',
            'target_value' => '200',
            'data' => [],
        ]);

        Sanctum::actingAs($merchant, ['surface:merchant']);

        $this
            ->withHeaders($this->tenantHeaders($alphaTenant->slug))
            ->getJson('http://merchant.company.com/api/admin/notification-inbox')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaNotification->id);

        $this
            ->withHeaders($this->tenantHeaders($alphaTenant->slug))
            ->patchJson("http://merchant.company.com/api/admin/notification-inbox/{$betaNotification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseHas('user_notifications', [
            'id' => $betaNotification->id,
            'read_at' => null,
        ]);
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
            'hostname' => "{$slug}.company.test",
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

    private function createUser(string $email, int $roleId, string $roleName): User
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

    private function attachTenantMember(Tenant $tenant, User $user): void
    {
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
    }

    private function createOrder(Tenant $tenant, User $customer): Order
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'Cash On Delivery',
            'slug' => 'cashondelivery',
            'status' => Status::ACTIVE,
        ]);

        return Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'ORD-'.Str::upper(Str::random(8)),
            'user_id' => $customer->id,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 100,
            'order_datetime' => now(),
            'payment_method' => $gateway->id,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'active' => Ask::NO,
        ]);
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
