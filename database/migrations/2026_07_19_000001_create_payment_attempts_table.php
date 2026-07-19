<?php

use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->foreignId('tenant_payment_method_id')->nullable()->constrained('tenant_payment_methods')->nullOnDelete();
            $table->string('gateway_slug', 80);
            $table->string('status', 30)->default(PaymentAttemptStatus::PENDING);
            $table->string('idempotency_key', 120)->unique();
            $table->string('provider_transaction_id', 190)->nullable();
            $table->decimal('amount', 19, 6)->default(0);
            $table->decimal('amount_verified', 19, 6)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->string('currency_verified', 10)->nullable();
            $table->boolean('backend_validation_passed')->default(false);
            $table->text('failure_reason')->nullable();
            $table->json('provider_payload_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['order_id', 'gateway_slug', 'status']);
            $table->index(['provider_transaction_id', 'gateway_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
