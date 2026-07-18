<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->decimal('yearly_price', 12, 2)->default(0);
            $table->unsignedInteger('trial_days')->default(0);
            $table->enum('transaction_fee_type', ['none', 'fixed', 'percent'])->default('none');
            $table->decimal('transaction_fee_value', 12, 4)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('platform_plan_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->string('limit_key', 80);
            $table->unsignedInteger('limit_value')->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->timestamps();
            $table->unique(['plan_id', 'limit_key']);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('platform_plans')->cascadeOnDelete();
            $table->string('plan_code_snapshot', 60);
            $table->string('plan_name_snapshot', 120);
            $table->enum('status', ['pending_activation', 'trialing', 'active', 'past_due', 'cancelled', 'expired'])->default('active');
            $table->enum('billing_interval', ['monthly', 'semiannual', 'yearly'])->default('monthly');
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('price_amount', 12, 2)->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_subscription_id')->constrained('tenant_subscriptions')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('invoice_no', 40)->unique();
            $table->enum('status', ['draft', 'open', 'paid', 'void'])->default('draft');
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('transaction_fee_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscription_invoices');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('platform_plan_limits');
        Schema::dropIfExists('platform_plans');
    }
};
