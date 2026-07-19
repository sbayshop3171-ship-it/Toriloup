<?php

namespace Tests\Feature;

use App\Enums\Activity;
use App\Enums\Ask;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Source;
use App\Enums\Status;
use App\Models\Barcode;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Slider;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPaymentMethod;
use App\Models\Unit;
use App\Models\User;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorefrontCustomerFlowTest extends TestCase
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
            'site_email_verification' => Activity::ENABLE,
            'site_phone_verification' => Activity::DISABLE,
            'site_cash_on_delivery' => Activity::ENABLE,
            'site_online_payment_gateway' => Activity::DISABLE,
        ]);

        Settings::group('otp')->set([
            'otp_expire_time' => 10,
        ]);
    }

    public function test_storefront_catalog_endpoints_are_scoped_to_the_store_host(): void
    {
        $alpha = $this->createTenant('alpha-store');
        $beta = $this->createTenant('beta-store');

        $alphaProduct = $this->createProductForTenant($alpha, 'Alpha Shirt', 'alpha-shirt', '1000001');
        $this->createProductForTenant($beta, 'Beta Shirt', 'beta-shirt', '1000002');
        $alphaSlider = Slider::withoutGlobalScopes()->create([
            'tenant_id' => $alpha->id,
            'title' => 'Alpha Hero',
            'link' => 'https://alpha.example.test',
            'description' => 'Alpha storefront banner',
            'status' => Status::ACTIVE,
        ]);
        $betaSlider = Slider::withoutGlobalScopes()->create([
            'tenant_id' => $beta->id,
            'title' => 'Beta Hero',
            'link' => 'https://beta.example.test',
            'description' => 'Beta storefront banner',
            'status' => Status::ACTIVE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://alpha-store.company.com/api/frontend/product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaProduct->id)
            ->assertJsonPath('data.0.slug', 'alpha-shirt');

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://alpha-store.company.com/api/frontend/slider?paginate=0&status='.Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaSlider->id)
            ->assertJsonPath('data.0.image', null);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://beta-store.company.com/api/frontend/slider?paginate=0&status='.Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $betaSlider->id);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://alpha-store.company.com/api/frontend/product/show/alpha-shirt')
            ->assertOk()
            ->assertJsonPath('data.id', $alphaProduct->id)
            ->assertJsonPath('data.slug', 'alpha-shirt');

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://beta-store.company.com/api/frontend/product/show/alpha-shirt')
            ->assertNotFound();
    }

    public function test_storefront_customer_endpoints_require_a_storefront_surface_token(): void
    {
        $tenant = $this->createTenant('surface-store');
        $customer = $this->createCustomerUser('surface-customer@test.com');

        $storefrontToken = $customer->createToken('storefront_auth_token', ['surface:storefront'])->plainTextToken;
        $merchantToken = $customer->createToken('merchant_auth_token', ['surface:merchant'])->plainTextToken;

        $this
            ->withHeaders($this->jsonHeaders($storefrontToken))
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/overview/total-orders")
            ->assertOk()
            ->assertJsonPath('data.total_orders', 0);

        $this
            ->withHeaders($this->jsonHeaders($merchantToken))
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/overview/total-orders")
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden. storefront token required.');
    }

    public function test_signup_otp_endpoints_skip_when_site_verification_is_disabled(): void
    {
        $tenant = $this->createTenant('signup-otp-store');

        Settings::group('site')->set([
            'site_email_verification' => Activity::DISABLE,
            'site_phone_verification' => Activity::DISABLE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/signup/otp-email", [
                'email' => 'signup-otp-skip@test.com',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('verification', false);

        $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/signup/otp-phone", [
                'country_code' => '+880',
                'phone' => '01711111111',
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('verification', false);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'signup-otp-skip@test.com',
        ]);
        $this->assertDatabaseMissing('otps', [
            'code' => '+880',
            'phone' => '01711111111',
        ]);
    }

    public function test_storefront_signup_registers_without_otp_when_email_verification_is_disabled(): void
    {
        $tenant = $this->createTenant('signup-register-store');
        $this->ensureCustomerRole();

        Settings::group('site')->set([
            'site_email_verification' => Activity::DISABLE,
            'site_phone_verification' => Activity::DISABLE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/signup/register", [
                'name' => 'Signup Disabled',
                'email' => 'signup-disabled@test.com',
                'password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'email' => 'signup-disabled@test.com',
            'is_guest' => Ask::NO,
        ]);
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'signup-disabled@test.com',
        ]);
    }

    public function test_storefront_payment_gateways_follow_merchant_enabled_methods(): void
    {
        $tenant = $this->createTenant('payment-method-store');

        PaymentGateway::query()->create([
            'name' => 'Cash on Delivery',
            'slug' => 'cashondelivery',
            'status' => Activity::ENABLE,
        ]);

        PaymentGateway::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'status' => Activity::ENABLE,
        ]);

        PaymentGateway::query()->create([
            'name' => 'Paypal',
            'slug' => 'paypal',
            'status' => Activity::ENABLE,
        ]);

        TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => 'cash_on_delivery',
            'display_name' => 'Pay Later',
            'status' => true,
            'checkout_label' => 'Pay when delivered',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => 'stripe',
            'display_name' => 'Card',
            'status' => false,
            'checkout_label' => 'Pay with card',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 2,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => 'paypal',
            'display_name' => 'Paypal Express',
            'status' => true,
            'checkout_label' => 'Pay with Paypal',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 3,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/payment-gateway?status=".Activity::ENABLE);

        $response->assertOk();

        $gateways = collect($response->json('data'));

        $this->assertSame(['cashondelivery', 'paypal'], $gateways->pluck('slug')->all());
        $this->assertSame('Pay Later', $gateways->firstWhere('slug', 'cashondelivery')['name']);
        $this->assertSame('Paypal Express', $gateways->firstWhere('slug', 'paypal')['name']);
        $this->assertNull($gateways->firstWhere('slug', 'stripe'));
    }

    public function test_storefront_payment_page_resolves_order_for_current_storefront_tenant(): void
    {
        $tenant = $this->createTenant('payment-page-store');
        $otherTenant = $this->createTenant('other-payment-page-store');
        $customer = $this->createCustomerUser('payment-page-customer@test.com');
        $currency = Currency::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dollars',
            'symbol' => '$',
            'code' => 'USD',
            'is_cryptocurrency' => 0,
            'exchange_rate' => 1,
        ]);
        $cashOnDelivery = PaymentGateway::query()->create([
            'name' => 'Cash on Delivery',
            'slug' => 'cashondelivery',
            'status' => Activity::ENABLE,
        ]);

        Settings::group('company')->set([
            'company_name' => 'Payment Page Store',
        ]);
        Settings::group('site')->set([
            'site_default_currency' => $currency->id,
            'site_cash_on_delivery' => Activity::ENABLE,
            'site_online_payment_gateway' => Activity::ENABLE,
        ]);
        DB::table('settings')->insert([
            [
                'group' => 'theme',
                'key' => 'theme_logo',
                'payload' => json_encode(null),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group' => 'theme',
                'key' => 'theme_favicon_logo',
                'payload' => json_encode(null),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => 'cash_on_delivery',
            'display_name' => 'Pay Later',
            'status' => true,
            'checkout_label' => 'Pay when delivered',
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);

        $order = Order::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'PAY-PAGE-1',
            'user_id' => $customer->id,
            'subtotal' => 150,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 150,
            'order_type' => OrderType::DELIVERY,
            'order_datetime' => now(),
            'payment_method' => $cashOnDelivery->id,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'source' => Source::WEB,
            'active' => Ask::NO,
        ]);

        $this
            ->get("http://{$tenant->slug}.company.com/payment/cashondelivery/pay/{$order->id}")
            ->assertOk();

        $this
            ->get("http://{$otherTenant->slug}.company.com/payment/cashondelivery/pay/{$order->id}")
            ->assertRedirect('/checkout/payment');
    }

    public function test_storefront_customer_can_create_address_place_order_and_complete_cod_checkout(): void
    {
        $tenant = $this->createTenant('checkout-store');
        $customer = $this->createCustomerUser('checkout-customer@test.com');
        $storefrontToken = $customer->createToken('storefront_auth_token', ['surface:storefront'])->plainTextToken;

        Country::query()->create([
            'name' => 'Bangladesh',
            'code' => 'BD',
            'currency_code' => 'BDT',
            'currency_symbol' => 'Tk',
            'status' => Status::ACTIVE,
        ]);

        $addressResponse = $this
            ->withHeaders($this->jsonHeaders($storefrontToken))
            ->postJson("http://{$tenant->slug}.company.com/api/frontend/address", [
                'full_name' => 'Checkout Customer',
                'email' => $customer->email,
                'country_code' => '+880',
                'phone' => '01700000000',
                'country' => 'Bangladesh',
                'state' => '',
                'city' => '',
                'zip_code' => '1207',
                'latitude' => null,
                'longitude' => null,
                'address' => 'House 10, Road 5',
            ]);

        $addressResponse
            ->assertCreated()
            ->assertJsonPath('data.full_name', 'Checkout Customer');

        $addressId = $addressResponse->json('data.id');

        $product = $this->createProductForTenant($tenant, 'Checkout Tee', 'checkout-tee', '1000100');
        $cashOnDelivery = PaymentGateway::query()->create([
            'name' => 'Cash on Delivery',
            'slug' => 'cashondelivery',
            'status' => Activity::ENABLE,
        ]);

        $orderResponse = $this
            ->withHeaders($this->jsonHeaders($storefrontToken))
            ->postJson("http://{$tenant->slug}.company.com/api/frontend/order", [
                'subtotal' => 120,
                'discount' => 0,
                'shipping_charge' => 10,
                'tax' => 0,
                'total' => 130,
                'order_type' => OrderType::DELIVERY,
                'shipping_id' => $addressId,
                'billing_id' => $addressId,
                'outlet_id' => 0,
                'coupon_id' => 0,
                'source' => Source::WEB,
                'payment_method' => $cashOnDelivery->id,
                'products' => json_encode([
                    [
                        'name' => $product->name,
                        'product_id' => $product->id,
                        'image' => $product->image,
                        'variation_names' => '',
                        'variation_id' => 0,
                        'sku' => $product->sku,
                        'stock' => 15,
                        'taxes' => [],
                        'quantity' => 1,
                        'discount' => 0,
                        'price' => 120,
                        'old_price' => 120,
                        'total_tax' => 0,
                        'subtotal' => 120,
                        'total' => 120,
                        'total_price' => 120,
                        'maximum_purchase_quantity' => 5,
                    ],
                ]),
            ]);

        $orderResponse
            ->assertCreated()
            ->assertJsonPath('data.user_id', $customer->id)
            ->assertJsonPath('data.payment_status', PaymentStatus::UNPAID);

        $orderId = $orderResponse->json('data.id');
        $order = Order::query()->findOrFail($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'tenant_id' => $tenant->id,
            'user_id' => $customer->id,
        ]);

        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $orderId,
            'user_id' => $customer->id,
            'address_type' => 5,
        ]);

        DB::table('capture_payment_notifications')->insert([
            'order_id' => $orderId,
            'token' => '987654321',
            'created_at' => now(),
        ]);

        $this
            ->get("http://{$tenant->slug}.company.com/payment/cashondelivery/{$orderId}/success?token=987654321")
            ->assertRedirect(route('payment.successful', ['order' => $orderId], false));

        $this
            ->get("http://{$tenant->slug}.company.com/payment/successful/{$orderId}")
            ->assertRedirect("/account/order-success/{$orderId}");

        $order->refresh();

        $this->assertSame(Ask::YES, (int) $order->active);
        $this->assertDatabaseHas('stocks', [
            'model_id' => $orderId,
            'model_type' => Order::class,
            'tenant_id' => $tenant->id,
            'status' => Status::ACTIVE,
        ]);
    }

    public function test_storefront_password_reset_returns_storefront_surface_context_and_shadow_customer(): void
    {
        $tenant = $this->createTenant('reset-store');
        $customer = $this->createCustomerUser('reset-customer@test.com');

        DB::table('password_reset_tokens')->insert([
            'email' => $customer->email,
            'token' => '1234',
            'is_verified' => Ask::YES,
            'created_at' => now(),
        ]);

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/forgot-password/reset-password", [
                'email' => $customer->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('surface', 'storefront')
            ->assertJsonPath('tenant.slug', $tenant->slug)
            ->assertJsonPath('domain.hostname', "{$tenant->slug}.company.com")
            ->assertJsonPath('status', true);

        $this->assertTrue(Hash::check('new-password', $customer->fresh()->password));
        $this->assertSame('storefront_auth_token', $customer->tokens()->latest('id')->first()?->name);
        $this->assertSame(['surface:storefront'], $customer->tokens()->latest('id')->first()?->abilities);
        $this->assertDatabaseHas((new Customer())->getTable(), [
            'tenant_id' => $tenant->id,
            'legacy_user_id' => $customer->id,
            'email' => $customer->email,
        ]);
    }

    private function jsonHeaders(?string $token = null): array
    {
        $headers = [
            'x-api-key' => 'testing-key',
            'x-localization' => 'en',
        ];

        if ($token !== null) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $headers;
    }

    private function createCustomerUser(string $email): User
    {
        $role = $this->ensureCustomerRole();

        $user = User::factory()->create([
            'name' => 'Storefront Customer',
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => Status::ACTIVE,
            'username' => 'customer-'.Str::random(6),
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function ensureCustomerRole(): Role
    {
        $role = Role::query()->find(LegacyRole::CUSTOMER);

        if ($role === null) {
            $role = new Role();
            $role->id = LegacyRole::CUSTOMER;
            $role->name = 'customer';
            $role->guard_name = 'sanctum';
            $role->save();
        }

        return $role;
    }

    private function createTenant(string $slug): Tenant
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

    private function createProductForTenant(Tenant $tenant, string $name, string $slug, string $sku): Product
    {
        $barcode = Barcode::query()->firstOrCreate(['name' => 'EAN 13']);

        $category = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => "{$name} Category",
            'slug' => "{$slug}-category",
            'status' => Status::ACTIVE,
        ]);

        $unit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Piece',
            'code' => Str::upper(Str::substr($slug, 0, 2)),
            'status' => Status::ACTIVE,
        ]);

        return Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'product_category_id' => $category->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unit->id,
            'buying_price' => 80,
            'selling_price' => 120,
            'variation_price' => 120,
            'status' => Status::ACTIVE,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 5,
            'low_stock_quantity_warning' => 1,
            'refundable' => 1,
            'shipping_type' => Activity::DISABLE,
            'shipping_cost' => 0,
            'is_product_quantity_multiply' => Ask::NO,
        ]);
    }
}
