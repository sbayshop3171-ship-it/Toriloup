<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $gateway = DB::table('payment_gateways')->where('slug', 'stripe')->first();

        if (!$gateway) {
            return;
        }

        $options = DB::table('gateway_options')
            ->where('model_type', 'App\\Models\\PaymentGateway')
            ->where('model_id', $gateway->id)
            ->whereIn('option', ['stripe_key', 'stripe_secret'])
            ->pluck('value', 'option');

        $stripeKey = trim((string) ($options['stripe_key'] ?? ''));
        $stripeSecret = trim((string) ($options['stripe_secret'] ?? ''));

        if (str_starts_with($stripeKey, 'sk_') && str_starts_with($stripeSecret, 'pk_')) {
            DB::table('gateway_options')
                ->where('model_type', 'App\\Models\\PaymentGateway')
                ->where('model_id', $gateway->id)
                ->where('option', 'stripe_key')
                ->update(['value' => $stripeSecret, 'updated_at' => now()]);

            DB::table('gateway_options')
                ->where('model_type', 'App\\Models\\PaymentGateway')
                ->where('model_id', $gateway->id)
                ->where('option', 'stripe_secret')
                ->update(['value' => $stripeKey, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
