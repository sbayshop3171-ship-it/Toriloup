<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_plans', function (Blueprint $table) {
            $table->string('short_description', 255)->nullable()->after('name');
            $table->boolean('is_public')->default(true)->after('status');
            $table->unsignedInteger('display_order')->default(0)->after('is_public');
            $table->boolean('is_recommended')->default(false)->after('display_order');
            $table->string('badge_label', 60)->nullable()->after('is_recommended');
        });

        Schema::create('platform_plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->enum('billing_interval', ['monthly', 'semiannual', 'yearly'])->default('monthly');
            $table->decimal('price_amount', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'billing_interval']);
        });

        Schema::create('platform_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->string('feature_code', 120);
            $table->string('display_label', 160);
            $table->string('compare_group', 120)->default('Operations');
            $table->enum('feature_type', ['boolean', 'text', 'integer', 'percent'])->default('boolean');
            $table->string('feature_value', 120)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'feature_code']);
        });

        Schema::create('subscription_checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('tenant_subscription_id');
            $table->foreignId('tenant_subscription_invoice_id');
            $table->string('provider_code', 80);
            $table->enum('status', ['pending', 'completed', 'cancelled', 'failed', 'expired'])->default('pending');
            $table->string('session_token', 100)->unique();
            $table->string('external_reference', 120)->nullable();
            $table->text('return_url')->nullable();
            $table->text('cancel_url')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['provider_code', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->foreign('tenant_id', 'sub_checkout_tenant_fk')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('tenant_subscription_id', 'sub_checkout_subscription_fk')->references('id')->on('tenant_subscriptions')->cascadeOnDelete();
            $table->foreign('tenant_subscription_invoice_id', 'sub_checkout_invoice_fk')->references('id')->on('tenant_subscription_invoices')->cascadeOnDelete();
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->timestamp('grace_ends_at')->nullable()->after('current_period_ends_at');
        });

        Schema::table('tenant_subscription_invoices', function (Blueprint $table) {
            $table->string('provider_code', 80)->nullable()->after('total_amount');
            $table->string('external_reference', 120)->nullable()->after('provider_code');
        });

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE platform_providers MODIFY provider_type ENUM('payment', 'sms', 'mail', 'push', 'analytics', 'domain', 'saas_billing') NOT NULL");
            DB::statement("ALTER TABLE tenant_feature_flags MODIFY source ENUM('platform_default', 'plan', 'owner_override', 'merchant_choice') NOT NULL DEFAULT 'platform_default'");
            DB::statement("ALTER TABLE tenant_subscriptions MODIFY status ENUM('pending_activation', 'trialing', 'active', 'past_due', 'cancelled', 'expired') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE tenant_subscriptions MODIFY billing_interval ENUM('monthly', 'semiannual', 'yearly') NOT NULL DEFAULT 'monthly'");
        }

        $plans = DB::table('platform_plans')->get(['id', 'monthly_price', 'yearly_price']);

        foreach ($plans as $plan) {
            DB::table('platform_plan_prices')->insert([
                [
                    'plan_id' => $plan->id,
                    'billing_interval' => 'monthly',
                    'price_amount' => $plan->monthly_price ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plan_id' => $plan->id,
                    'billing_interval' => 'semiannual',
                    'price_amount' => round(((float) ($plan->monthly_price ?? 0)) * 6, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plan_id' => $plan->id,
                    'billing_interval' => 'yearly',
                    'price_amount' => $plan->yearly_price ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('tenant_feature_flags')->where('source', 'plan')->update(['source' => 'platform_default']);
            DB::table('tenant_subscriptions')->where('status', 'pending_activation')->update(['status' => 'cancelled']);
            DB::table('tenant_subscriptions')->where('billing_interval', 'semiannual')->update(['billing_interval' => 'monthly']);
            DB::statement("ALTER TABLE tenant_feature_flags MODIFY source ENUM('platform_default', 'owner_override', 'merchant_choice') NOT NULL DEFAULT 'platform_default'");
            DB::statement("ALTER TABLE tenant_subscriptions MODIFY status ENUM('trialing', 'active', 'past_due', 'cancelled', 'expired') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE tenant_subscriptions MODIFY billing_interval ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly'");
            DB::statement("ALTER TABLE platform_providers MODIFY provider_type ENUM('payment', 'sms', 'mail', 'push', 'analytics', 'domain') NOT NULL");
        }

        Schema::table('tenant_subscription_invoices', function (Blueprint $table) {
            $table->dropColumn(['provider_code', 'external_reference']);
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropColumn('grace_ends_at');
        });

        Schema::dropIfExists('subscription_checkout_sessions');
        Schema::dropIfExists('platform_plan_features');
        Schema::dropIfExists('platform_plan_prices');

        Schema::table('platform_plans', function (Blueprint $table) {
            $table->dropColumn(['short_description', 'is_public', 'display_order', 'is_recommended', 'badge_label']);
        });
    }
};
