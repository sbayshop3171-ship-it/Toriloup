<?php

namespace Tests\Feature;

use App\Enums\Activity;
use App\Enums\Ask;
use App\Enums\InputType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role as LegacyRole;
use App\Enums\Source;
use App\Enums\Status;
use App\Events\SendOrderGotMail;
use App\Events\SendOrderGotPush;
use App\Events\SendOrderGotSms;
use App\Events\SendOrderMail;
use App\Events\SendOrderPush;
use App\Events\SendOrderSms;
use App\Http\Resources\UserResource;
use App\Models\Barcode;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\MerchantWallet;
use App\Models\MerchantWalletTransaction;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Slider;
use App\Models\State;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPaymentMethod;
use App\Models\Unit;
use App\Models\User;
use App\Services\Currency\CurrencyCatalogService;
use App\Services\IpLocationService;
use App\Services\PaymentAttemptService;
use App\Services\Saas\MerchantWalletService;
use App\Services\Saas\TenantSettingsService;
use Dipokhalder\Settings\Facades\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

    public function test_store_base_currency_is_original_price_and_ip_changes_display_currency(): void
    {
        $tenant = $this->createTenant('bdt-base-currency-store');
        $this->configureTenantCurrency($tenant, 'BDT');
        $this->createProductForTenant($tenant, 'BDT Product', 'bdt-product', '1000201');

        $localResponse = $this
            ->withHeaders($this->jsonHeaders() + ['CF-IPCountry' => 'BD'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $localResponse->assertOk();
        $this->assertSame('BDT', $localResponse->json('data.0.base_currency_code'));
        $this->assertSame('BDT', $localResponse->json('data.0.display_currency_code'));
        $this->assertEquals(120.0, (float) $localResponse->json('data.0.price'));
        $this->assertSame('Tk120.00', $localResponse->json('data.0.currency_price'));

        $foreignResponse = $this
            ->withHeaders($this->jsonHeaders() + ['CF-IPCountry' => 'US'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $foreignResponse->assertOk();
        $this->assertSame('BDT', $foreignResponse->json('data.0.base_currency_code'));
        $this->assertSame('USD', $foreignResponse->json('data.0.display_currency_code'));
        $this->assertEqualsWithDelta(0.97, (float) $foreignResponse->json('data.0.price'), 0.01);
        $this->assertSame('$0.97', $foreignResponse->json('data.0.currency_price'));
    }

    public function test_usd_base_currency_converts_to_bdt_for_bangladesh_visitors(): void
    {
        $tenant = $this->createTenant('usd-base-currency-store');
        $this->configureTenantCurrency($tenant, 'USD');
        $this->createProductForTenant($tenant, 'USD Product', 'usd-product', '1000202');

        $response = $this
            ->withHeaders($this->jsonHeaders() + ['CF-IPCountry' => 'BD'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $response->assertOk();
        $this->assertSame('USD', $response->json('data.0.base_currency_code'));
        $this->assertSame('BDT', $response->json('data.0.display_currency_code'));
        $this->assertEquals(14803.2, (float) $response->json('data.0.price'));
        $this->assertSame('Tk14803.20', $response->json('data.0.currency_price'));
    }

    public function test_storefront_currency_uses_ip_location_when_country_header_is_missing(): void
    {
        $tenant = $this->createTenant('ip-location-currency-store');
        $this->configureTenantCurrency($tenant, 'USD');
        $this->createProductForTenant($tenant, 'IP Location Product', 'ip-location-product', '1000204');

        $this->mock(IpLocationService::class, function ($mock): void {
            $mock->shouldReceive('detect')
                ->once()
                ->andReturn(['country_code' => 'BD']);
        });

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $response->assertOk();
        $this->assertSame('USD', $response->json('data.0.base_currency_code'));
        $this->assertSame('BDT', $response->json('data.0.display_currency_code'));
        $this->assertEquals(14803.2, (float) $response->json('data.0.price'));
        $this->assertSame('Tk14803.20', $response->json('data.0.currency_price'));
    }

    public function test_merchant_can_disable_auto_visitor_currency_but_manual_currency_still_works(): void
    {
        $tenant = $this->createTenant('manual-currency-store');
        $this->configureTenantCurrency($tenant, 'USD', Activity::DISABLE);
        $this->createProductForTenant($tenant, 'Manual Currency Product', 'manual-currency-product', '1000203');

        $autoDisabledResponse = $this
            ->withHeaders($this->jsonHeaders() + ['CF-IPCountry' => 'BD'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $autoDisabledResponse->assertOk();
        $this->assertSame('USD', $autoDisabledResponse->json('data.0.display_currency_code'));
        $this->assertEquals(120.0, (float) $autoDisabledResponse->json('data.0.price'));

        $manualResponse = $this
            ->withHeaders($this->jsonHeaders() + ['CF-IPCountry' => 'BD', 'X-Currency-Code' => 'BDT'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/product");

        $manualResponse->assertOk();
        $this->assertSame('BDT', $manualResponse->json('data.0.display_currency_code'));
        $this->assertEquals(14803.2, (float) $manualResponse->json('data.0.price'));
    }

    public function test_currency_catalog_seed_preserves_synced_rates_across_requests(): void
    {
        $tenant = $this->createTenant('currency-rate-preserve-store');
        $catalog = app(CurrencyCatalogService::class);
        $catalog->ensureTenantCurrencies($tenant);

        foreach ([null, $tenant->id] as $tenantId) {
            Currency::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', 'BDT')
                ->first()
                ?->forceFill([
                    'exchange_rate' => 123.36,
                    'rate_source' => 'open_er_api',
                    'rate_synced_at' => now(),
                    'is_auto_managed' => true,
                ])
                ->save();
        }

        $this->resetCurrencyCatalogState();
        app(CurrencyCatalogService::class)->ensureTenantCurrencies($tenant);

        $globalBdt = Currency::withoutGlobalScopes()->whereNull('tenant_id')->where('code', 'BDT')->first();
        $tenantBdt = Currency::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'BDT')->first();

        $this->assertEqualsWithDelta(123.36, (float) $globalBdt->exchange_rate, 0.001);
        $this->assertSame('open_er_api', $globalBdt->rate_source);
        $this->assertEqualsWithDelta(123.36, (float) $tenantBdt->exchange_rate, 0.001);
        $this->assertSame('open_er_api', $tenantBdt->rate_source);
    }

    public function test_storefront_sliders_use_only_current_merchant_sliders(): void
    {
        $tenant = $this->createTenant('fallback-slider-store');
        Slider::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'title' => 'Global Hero',
            'link' => 'https://owner.example.test/default',
            'description' => 'Shared owner banner',
            'status' => Status::ACTIVE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/slider?paginate=0&status=".Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $tenantSlider = Slider::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Merchant Hero',
            'link' => 'https://merchant.example.test/banner',
            'description' => 'Merchant-owned banner',
            'status' => Status::ACTIVE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/slider?paginate=0&status=".Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tenantSlider->id);

        $tenantSlider->delete();

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/slider?paginate=0&status=".Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_storefront_sliders_do_not_fallback_to_owner_defaults_without_a_resolved_tenant(): void
    {
        Slider::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'title' => 'Global Hero',
            'link' => 'https://owner.example.test/default',
            'description' => 'Shared owner banner',
            'status' => Status::ACTIVE,
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://unknown.company.com/api/frontend/slider?paginate=0&status='.Status::ACTIVE)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_storefront_location_detects_country_from_client_ip(): void
    {
        $tenant = $this->createTenant('location-store');

        Country::query()->create([
            'name' => 'Bangladesh',
            'code' => 'BD',
            'currency_code' => 'BDT',
            'currency_symbol' => 'Tk',
            'status' => Status::ACTIVE,
        ]);

        Http::fake([
            'ipapi.co/*' => Http::response([
                'country_code' => 'BD',
                'country_name' => 'Bangladesh',
                'region' => 'Dhaka',
                'city' => 'Dhaka',
                'postal' => '1216',
                'latitude' => 23.8103,
                'longitude' => 90.4125,
            ]),
        ]);

        $this
            ->withHeaders($this->jsonHeaders())
            ->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/location/detect")
            ->assertOk()
            ->assertJsonPath('data.country_code', 'BD')
            ->assertJsonPath('data.country_name', 'Bangladesh')
            ->assertJsonPath('data.calling_code', '+880')
            ->assertJsonPath('data.flag_emoji', '🇧🇩')
            ->assertJsonPath('data.state', 'Dhaka')
            ->assertJsonPath('data.city', 'Dhaka')
            ->assertJsonPath('data.zip_code', '1216')
            ->assertJsonPath('data.latitude', 23.8103)
            ->assertJsonPath('data.longitude', 90.4125);
    }

    public function test_storefront_reverse_location_uses_server_side_geocoding_fallback(): void
    {
        $tenant = $this->createTenant('reverse-location-store');
        $country = Country::query()->create([
            'name' => 'Bangladesh',
            'code' => 'BD',
            'currency_code' => 'BDT',
            'currency_symbol' => 'Tk',
            'status' => Status::ACTIVE,
        ]);
        State::query()->create([
            'name' => 'Chittagong Division',
            'country_id' => $country->id,
            'status' => Status::ACTIVE,
        ]);
        $storedState = State::query()->create([
            'name' => 'Chattagam',
            'country_id' => $country->id,
            'status' => Status::ACTIVE,
        ]);
        City::query()->create([
            'name' => 'Chattagam',
            'state_id' => $storedState->id,
            'status' => Status::ACTIVE,
        ]);

        config(['services.mapbox.access_token' => null]);

        Http::fake([
            'https://nominatim.openstreetmap.org/*' => Http::response([
                'osm_id' => 123456,
                'display_name' => 'Halishahar Road, Halishahar, Chattogram, Chattogram Division, Bangladesh, 4216',
                'address' => [
                    'road' => 'Halishahar Road',
                    'suburb' => 'Halishahar',
                    'city' => 'Chattogram',
                    'state' => 'Chattogram Division',
                    'postcode' => '4216',
                    'country' => 'Bangladesh',
                    'country_code' => 'bd',
                ],
            ], 200),
        ]);

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/location/reverse?latitude=22.3419&longitude=91.7908");

        $response
            ->assertOk()
            ->assertJsonPath('data.country_code', 'BD')
            ->assertJsonPath('data.country', 'Bangladesh')
            ->assertJsonPath('data.state', 'Chattogram Division')
            ->assertJsonPath('data.city', 'Chattogram')
            ->assertJsonPath('data.stored_state', 'Chattagam')
            ->assertJsonPath('data.stored_city', 'Chattagam')
            ->assertJsonPath('data.zip_code', '4216')
            ->assertJsonPath('data.source', 'nominatim');

        $this->assertStringContainsString('Halishahar Road', $response->json('data.street_address'));
        $this->assertSame(22.3419, (float) $response->json('data.latitude'));
        $this->assertSame(91.7908, (float) $response->json('data.longitude'));
    }

    public function test_storefront_states_include_active_city_counts_for_location_matching(): void
    {
        $tenant = $this->createTenant('state-city-location-store');
        $country = Country::query()->create([
            'name' => 'Bangladesh',
            'code' => 'BD',
            'currency_code' => 'BDT',
            'currency_symbol' => 'Tk',
            'status' => Status::ACTIVE,
        ]);
        State::query()->create([
            'name' => 'Chittagong Division',
            'country_id' => $country->id,
            'status' => Status::ACTIVE,
        ]);
        $stateWithCities = State::query()->create([
            'name' => 'Chattagam',
            'country_id' => $country->id,
            'status' => Status::ACTIVE,
        ]);
        City::query()->create([
            'name' => 'Chattagam',
            'state_id' => $stateWithCities->id,
            'status' => Status::ACTIVE,
        ]);

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/country-state-city/states/Bangladesh")
            ->assertOk();

        $states = collect($response->json('data'))->keyBy('name');

        $this->assertSame(1, $states->get('Chattagam')['active_cities_count']);
        $this->assertSame(0, $states->get('Chittagong Division')['active_cities_count']);
    }

    public function test_profile_resource_falls_back_when_profile_media_file_is_missing(): void
    {
        $user = User::factory()->create([
            'name' => 'Missing Avatar Customer',
            'email' => 'missing-avatar@test.com',
            'username' => 'missing-avatar',
            'status' => Status::ACTIVE,
        ]);

        Media::query()->create([
            'model_type' => User::class,
            'model_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'profile',
            'name' => 'missing-profile',
            'file_name' => 'missing-profile.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => ['thumb' => true],
            'responsive_images' => [],
            'order_column' => 1,
        ]);

        $payload = (new UserResource($user->fresh()))->resolve(request());

        $this->assertSame(asset('images/required/profile.png'), $payload['image']);
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

    public function test_storefront_signup_login_verify_returns_storefront_token_for_customer_session(): void
    {
        $tenant = $this->createTenant('signup-session-store');
        $this->ensureCustomerRole();

        Settings::group('site')->set([
            'site_email_verification' => Activity::DISABLE,
            'site_phone_verification' => Activity::DISABLE,
        ]);

        $payload = [
            'name' => 'Signup Session',
            'email' => 'signup-session@test.com',
            'password' => 'secret123',
        ];

        $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/signup/register", $payload)
            ->assertOk()
            ->assertJsonPath('status', true);

        $response = $this
            ->withHeaders($this->jsonHeaders())
            ->postJson("http://{$tenant->slug}.company.com/api/storefront/auth/signup/login-verify", $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('surface', 'storefront')
            ->assertJsonPath('tenant.slug', $tenant->slug)
            ->assertJsonPath('domain.hostname', "{$tenant->slug}.company.com");

        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);

        $user = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertSame('storefront_auth_token', $user->tokens()->latest('id')->first()?->name);
        $this->assertSame(['surface:storefront'], $user->tokens()->latest('id')->first()?->abilities);

        $this
            ->withHeaders($this->jsonHeaders($token))
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/overview/total-orders")
            ->assertOk()
            ->assertJsonPath('data.total_orders', 0);

        $this->assertDatabaseHas((new Customer())->getTable(), [
            'tenant_id' => $tenant->id,
            'legacy_user_id' => $user->id,
            'email' => $payload['email'],
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

    public function test_storefront_stripe_payment_page_follows_owner_and_merchant_status(): void
    {
        $tenant = $this->createTenant('stripe-payment-store');
        $customer = $this->createCustomerUser('stripe-payment-customer@test.com');
        $this->preparePaymentPageSettings($tenant, 'Stripe Payment Store');
        $stripe = $this->createStripeGateway();

        $method = $this->createTenantGatewayMethod($tenant, 'stripe', true, 'Card');
        $order = $this->createPaymentOrder($tenant, $customer, $stripe, 225, 'STRIPE-PAGE-1');

        $this
            ->get("http://{$tenant->slug}.company.com/payment/stripe/pay/{$order->id}")
            ->assertOk()
            ->assertSee('paymentForm', false)
            ->assertSee('pk_test_storefront', false);

        $method->update(['status' => false]);

        $this
            ->get("http://{$tenant->slug}.company.com/payment/stripe/pay/{$order->id}")
            ->assertRedirect('/checkout/payment');

        $method->update(['status' => true]);
        $stripe->update(['status' => Activity::DISABLE]);

        $this
            ->get("http://{$tenant->slug}.company.com/payment/stripe/pay/{$order->id}")
            ->assertRedirect('/checkout/payment');
    }

    public function test_storefront_payment_page_accepts_global_currency_setting_under_tenant_scope(): void
    {
        $tenant = $this->createTenant('global-currency-payment-store');
        $customer = $this->createCustomerUser('global-currency-payment-customer@test.com');
        $globalCurrency = Currency::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Jordanian Dinar',
            'symbol' => 'JD',
            'code' => 'JOD',
            'is_cryptocurrency' => 0,
            'exchange_rate' => 1,
        ]);

        $this->preparePaymentPageSettings($tenant, 'Global Currency Payment Store');
        Settings::group('site')->set([
            'site_default_currency' => $globalCurrency->id,
            'site_default_currency_code' => 'JOD',
            'site_default_currency_symbol' => 'JD',
        ]);

        $stripe = $this->createStripeGateway();
        $this->createTenantGatewayMethod($tenant, 'stripe');
        $order = $this->createPaymentOrder($tenant, $customer, $stripe, 225, 'GLOBAL-CURRENCY-PAGE-1');

        $this
            ->get("http://{$tenant->slug}.company.com/payment/stripe/pay/{$order->id}")
            ->assertOk()
            ->assertSee('paymentForm', false);
    }

    public function test_storefront_stripe_success_settles_merchant_wallet_and_keeps_notifications_single(): void
    {
        config(['saas.wallet.holding_days' => 0]);

        $tenant = $this->createTenant('stripe-success-store');
        $customer = $this->createCustomerUser('stripe-success-customer@test.com');
        $this->preparePaymentPageSettings($tenant, 'Stripe Success Store');
        $stripe = $this->createStripeGateway();
        $this->createTenantGatewayMethod($tenant, 'stripe');
        $order = $this->createPaymentOrder($tenant, $customer, $stripe, 310, 'STRIPE-SUCCESS-1');

        app(PaymentAttemptService::class)->prepare($order, 'stripe', 'stripe-success-attempt');

        DB::table('capture_payment_notifications')->insert([
            'order_id' => $order->id,
            'token' => 'bt_test_storefront_123',
            'created_at' => now(),
        ]);

        Event::fake([
            SendOrderMail::class,
            SendOrderSms::class,
            SendOrderPush::class,
            SendOrderGotMail::class,
            SendOrderGotSms::class,
            SendOrderGotPush::class,
        ]);

        $this
            ->get("http://{$tenant->slug}.company.com/payment/stripe/{$order->id}/success?token=bt_test_storefront_123")
            ->assertRedirect(route('payment.successful', ['order' => $order->id], false));

        $this
            ->get("http://{$tenant->slug}.company.com/payment/successful/{$order->id}")
            ->assertRedirect("/account/order-success/{$order->id}");

        $order->refresh();

        $this->assertSame(Ask::YES, (int) $order->active);
        $this->assertSame(PaymentStatus::PAID, (int) $order->payment_status);
        $this->assertDatabaseHas('transactions', [
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'transaction_no' => 'bt_test_storefront_123',
            'payment_method' => 'stripe',
            'type' => 'payment',
            'sign' => '+',
        ]);

        $wallet = MerchantWallet::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertEquals(310.0, (float) $wallet->available_balance);
        $this->assertEquals(310.0, (float) $wallet->total_earned);
        $this->assertSame(1, MerchantWalletTransaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('order_id', $order->id)
            ->where('type', MerchantWalletService::TYPE_ORDER_PAYMENT)
            ->count());

        $attempt = PaymentAttempt::withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(PaymentAttemptStatus::SUCCEEDED, $attempt->status);
        $this->assertSame('bt_test_storefront_123', $attempt->provider_transaction_id);
        $this->assertTrue($attempt->backend_validation_passed);

        Event::assertDispatchedTimes(SendOrderMail::class, 1);
        Event::assertDispatchedTimes(SendOrderSms::class, 1);
        Event::assertDispatchedTimes(SendOrderPush::class, 1);
        Event::assertDispatchedTimes(SendOrderGotMail::class, 1);
        Event::assertDispatchedTimes(SendOrderGotSms::class, 1);
        Event::assertDispatchedTimes(SendOrderGotPush::class, 1);
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

    public function test_storefront_country_code_show_null_returns_empty_payload_instead_of_crashing(): void
    {
        $tenant = $this->createTenant('country-code-null-store');

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson("http://{$tenant->slug}.company.com/api/frontend/country-code/show/null")
            ->assertOk()
            ->assertJsonPath('data.calling_code', null)
            ->assertJsonPath('data.flag_emoji', '');
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

    private function configureTenantCurrency(Tenant $tenant, string $currencyCode, int $autoVisitorCurrency = Activity::ENABLE): void
    {
        $currencyCode = strtoupper($currencyCode);
        $countryCode = $currencyCode === 'BDT' ? 'BD' : 'US';

        $tenant->forceFill([
            'country_code' => $countryCode,
            'primary_currency_code' => $currencyCode,
        ])->save();

        $catalog = app(CurrencyCatalogService::class);
        $catalog->ensureTenantCurrencies($tenant);
        $this->seedTestCurrencyRates($tenant);
        $currency = $catalog->findByCode($currencyCode, $tenant);

        app(TenantSettingsService::class)->seedDefaultsForTenant($tenant, [
            'company_country_code' => $countryCode,
            'site_default_currency' => $currency?->id ?? 1,
            'site_default_currency_code' => $currencyCode,
            'site_default_currency_symbol' => $currency?->symbol ?? $currencyCode,
            'site_auto_visitor_currency' => $autoVisitorCurrency,
        ]);
    }

    private function seedTestCurrencyRates(Tenant $tenant): void
    {
        foreach ([
            'USD' => ['symbol' => '$', 'rate' => 1],
            'BDT' => ['symbol' => 'Tk', 'rate' => 123.36],
            'EUR' => ['symbol' => '€', 'rate' => 0.88],
        ] as $code => $data) {
            $currency = Currency::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            if ($currency instanceof Currency) {
                $currency->forceFill([
                    'symbol' => $data['symbol'],
                    'exchange_rate' => $data['rate'],
                    'rate_source' => 'test',
                    'rate_synced_at' => now(),
                ])->save();
            }
        }
    }

    private function resetCurrencyCatalogState(): void
    {
        $reflection = new \ReflectionClass(CurrencyCatalogService::class);

        $globalSeeded = $reflection->getProperty('globalSeeded');
        $globalSeeded->setAccessible(true);
        $globalSeeded->setValue(null, false);

        $tenantEnsured = $reflection->getProperty('tenantEnsured');
        $tenantEnsured->setAccessible(true);
        $tenantEnsured->setValue(null, []);
    }

    private function preparePaymentPageSettings(Tenant $tenant, string $companyName): Currency
    {
        $currency = Currency::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dollars',
            'symbol' => '$',
            'code' => 'USD',
            'is_cryptocurrency' => 0,
            'exchange_rate' => 1,
        ]);

        Settings::group('company')->set([
            'company_name' => $companyName,
        ]);
        Settings::group('site')->set([
            'site_default_currency' => $currency->id,
            'site_cash_on_delivery' => Activity::ENABLE,
            'site_online_payment_gateway' => Activity::ENABLE,
        ]);

        foreach (['theme_logo', 'theme_favicon_logo'] as $key) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'theme', 'key' => $key],
                [
                    'payload' => json_encode(null),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $currency;
    }

    private function createStripeGateway(int $status = Activity::ENABLE): PaymentGateway
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'misc' => json_encode([
                'input' => ['stripe.stripeInput.blade.php'],
                'js' => ['stripe.stripeJs.blade.php'],
                'onClick' => false,
                'submit' => true,
            ]),
            'status' => $status,
        ]);

        $gateway->gatewayOptions()->create([
            'option' => 'stripe_key',
            'value' => 'pk_test_storefront',
            'type' => InputType::TEXT,
            'activities' => '',
        ]);
        $gateway->gatewayOptions()->create([
            'option' => 'stripe_secret',
            'value' => 'sk_test_storefront',
            'type' => InputType::TEXT,
            'activities' => '',
        ]);

        return $gateway->fresh('gatewayOptions');
    }

    private function createTenantGatewayMethod(Tenant $tenant, string $providerCode, bool $status = true, ?string $displayName = null): TenantPaymentMethod
    {
        return TenantPaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'provider_code' => $providerCode,
            'display_name' => $displayName ?: Str::headline($providerCode),
            'status' => $status,
            'checkout_label' => 'Pay with '.($displayName ?: Str::headline($providerCode)),
            'fee_type' => 'none',
            'fee_value' => 0,
            'sort_order' => 1,
            'config_json' => ['managed_by' => 'owner'],
        ]);
    }

    private function createPaymentOrder(Tenant $tenant, User $customer, PaymentGateway $gateway, float $total, string $serial): Order
    {
        return Order::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => $serial,
            'user_id' => $customer->id,
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => $total,
            'order_type' => OrderType::DELIVERY,
            'order_datetime' => now(),
            'payment_method' => $gateway->id,
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'source' => Source::WEB,
            'active' => Ask::NO,
        ]);
    }
}
