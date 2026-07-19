<?php

namespace Tests\Feature;

use App\Enums\Activity;
use App\Enums\InputType;
use App\Http\PaymentGateways\Requests\Paypal as PaypalSettingsRequest;
use App\Http\Resources\PaymentGatewayResource;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaypalGatewayConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_admin_settings_hide_app_id_option(): void
    {
        $gateway = PaymentGateway::query()->create([
            'name' => 'Paypal',
            'slug' => 'paypal',
            'status' => Activity::DISABLE,
        ]);

        $gateway->gatewayOptions()->create([
            'option' => 'paypal_app_id',
            'value' => 'legacy-app-id',
            'type' => InputType::TEXT,
            'activities' => '',
        ]);

        $gateway->gatewayOptions()->create([
            'option' => 'paypal_client_id',
            'value' => 'client-id',
            'type' => InputType::TEXT,
            'activities' => '',
        ]);

        $gateway->gatewayOptions()->create([
            'option' => 'paypal_client_secret',
            'value' => 'client-secret',
            'type' => InputType::TEXT,
            'activities' => '',
        ]);

        $data = (new PaymentGatewayResource($gateway->fresh('gatewayOptions')))->resolve(request());
        $options = collect($data['options'])->pluck('option')->all();

        $this->assertNotContains('paypal_app_id', $options);
        $this->assertContains('paypal_client_id', $options);
        $this->assertContains('paypal_client_secret', $options);
    }

    public function test_paypal_settings_do_not_require_app_id_when_enabled(): void
    {
        $request = Request::create('/api/admin/payment-gateway', 'PUT', [
            'payment_type' => 'paypal',
            'paypal_status' => Activity::ENABLE,
            'paypal_mode' => '5',
            'paypal_client_id' => 'client-id',
            'paypal_client_secret' => 'client-secret',
        ]);

        app()->instance('request', $request);

        $rules = (new PaypalSettingsRequest())->rules();

        $this->assertArrayNotHasKey('paypal_app_id', $rules);
        $this->assertArrayHasKey('paypal_client_id', $rules);
        $this->assertArrayHasKey('paypal_client_secret', $rules);
    }
}
