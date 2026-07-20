<?php

namespace Tests\Feature;

use App\Enums\Activity;
use App\Enums\GatewayMode;
use App\Enums\InputType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Status;
use App\Models\Currency;
use App\Models\Order;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\PaymentAbstract;
use App\Services\PaymentGatewayService;
use App\Services\PaymentService;
use App\Support\PaymentGatewayCredentials;
use App\Support\StorefrontBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StripeGatewayConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_credentials_resolve_when_publishable_and_secret_keys_are_reversed(): void
    {
        $options = collect([
            'stripe_key' => 'sk_live_secret_from_wrong_field',
            'stripe_secret' => 'pk_live_publishable_from_wrong_field',
        ]);

        $this->assertSame('pk_live_publishable_from_wrong_field', PaymentGatewayCredentials::stripePublishableKey($options));
        $this->assertSame('sk_live_secret_from_wrong_field', PaymentGatewayCredentials::stripeSecretKey($options));
    }

    public function test_stripe_settings_save_normalizes_reversed_keys(): void
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'status' => Activity::DISABLE,
        ]);

        foreach (['stripe_key', 'stripe_secret', 'stripe_mode', 'stripe_status'] as $option) {
            $gateway->gatewayOptions()->create([
                'option' => $option,
                'value' => '',
                'type' => $option === 'stripe_mode' || $option === 'stripe_status' ? InputType::SELECT : InputType::TEXT,
                'activities' => '',
            ]);
        }

        app(PaymentGatewayService::class)->update([
            'stripe_key' => 'sk_live_secret_from_wrong_field',
            'stripe_secret' => 'pk_live_publishable_from_wrong_field',
            'stripe_mode' => GatewayMode::LIVE,
            'stripe_status' => Activity::ENABLE,
        ]);

        $options = $gateway->fresh('gatewayOptions')->gatewayOptions->pluck('value', 'option');

        $this->assertSame('pk_live_publishable_from_wrong_field', $options->get('stripe_key'));
        $this->assertSame('sk_live_secret_from_wrong_field', $options->get('stripe_secret'));
        $this->assertSame(Activity::ENABLE, (int) $gateway->fresh()->status);
    }

    public function test_gateway_amount_falls_back_to_supported_currency_with_conversion(): void
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'BDT Store',
            'slug' => 'bdt-store',
            'store_code' => 'BDTSTORE',
            'status' => 'active',
            'primary_currency_code' => 'BDT',
            'country_code' => 'BD',
        ]);
        $customer = User::factory()->create([
            'username' => 'gateway-customer',
            'status' => Status::ACTIVE,
        ]);

        Currency::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Bangladeshi Taka',
            'symbol' => 'Tk',
            'code' => 'BDT',
            'minor_unit' => 2,
            'is_cryptocurrency' => 0,
            'exchange_rate' => 120,
            'is_enabled' => true,
        ]);
        Currency::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'US Dollar',
            'symbol' => '$',
            'code' => 'USD',
            'minor_unit' => 2,
            'is_cryptocurrency' => 0,
            'exchange_rate' => 1,
            'is_enabled' => true,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'order_serial_no' => 'ORD-FX-1',
            'user_id' => $customer->id,
            'subtotal' => 1200,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 1200,
            'order_datetime' => now(),
            'payment_status' => PaymentStatus::UNPAID,
            'status' => OrderStatus::PENDING,
            'charge_currency_code' => 'BDT',
            'display_currency_code' => 'BDT',
        ]);

        $gateway = new class(new PaymentService()) extends PaymentAbstract {
            public function exposedGatewayPaymentAmount(Order $order): array
            {
                return $this->gatewayPaymentAmount($order, 'USD', ['USD']);
            }

            public function status()
            {
                return true;
            }

            public function payment($order, $request)
            {
            }

            public function success($order, $request)
            {
            }

            public function fail($order, $request)
            {
            }

            public function cancel($order, $request)
            {
            }
        };

        $charge = $gateway->exposedGatewayPaymentAmount($order);

        $this->assertSame('USD', $charge['currency']);
        $this->assertSame(10.0, $charge['amount']);
    }

    public function test_stripe_card_form_renders_explicit_card_entry_fields(): void
    {
        $html = view('paymentGateways.stripe.stripeInput')->render();

        $this->assertStringContainsString('Payment method', $html);
        $this->assertStringContainsString('Card information', $html);
        $this->assertStringContainsString('card-number-element', $html);
        $this->assertStringContainsString('card-expiry-element', $html);
        $this->assertStringContainsString('card-cvc-element', $html);
        $this->assertStringContainsString('card-holder-name', $html);
    }

    public function test_storefront_branding_prefers_tenant_logo_and_stays_blank_without_one(): void
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Brand Store',
            'slug' => 'brand-store',
            'store_code' => 'BRANDSTORE',
            'status' => 'active',
            'primary_currency_code' => 'USD',
            'country_code' => 'US',
        ]);

        $this->assertNull(app(StorefrontBranding::class)->logoUrl($tenant));

        TenantSetting::query()->create([
            'tenant_id' => $tenant->id,
            'group_key' => 'company',
            'setting_key' => 'company_logo',
            'setting_value' => 'tenants/'.$tenant->id.'/branding/store-logo.png',
            'value_type' => 'string',
            'is_encrypted' => false,
        ]);

        $this->assertStringContainsString(
            '/storage/tenants/'.$tenant->id.'/branding/store-logo.png',
            app(StorefrontBranding::class)->logoUrl($tenant)
        );
    }
}
