<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('available_balance', 19, 6)->default(0);
            $table->decimal('holding_balance', 19, 6)->default(0);
            $table->decimal('pending_withdrawal_balance', 19, 6)->default(0);
            $table->decimal('total_earned', 19, 6)->default(0);
            $table->decimal('total_withdrawn', 19, 6)->default(0);
            $table->decimal('total_fees', 19, 6)->default(0);
            $table->decimal('total_refunded', 19, 6)->default(0);
            $table->timestamp('last_settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_payout_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->json('fields_json')->nullable();
            $table->boolean('status')->default(true);
            $table->decimal('min_amount', 19, 6)->default(0);
            $table->decimal('max_amount', 19, 6)->nullable();
            $table->enum('fee_type', ['none', 'fixed', 'percent'])->default('none');
            $table->decimal('fee_value', 19, 6)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('merchant_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('request_no', 40)->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('merchant_wallets')->cascadeOnDelete();
            $table->foreignId('payout_method_id')->constrained('merchant_payout_methods')->restrictOnDelete();
            $table->decimal('amount', 19, 6);
            $table->decimal('fee_amount', 19, 6)->default(0);
            $table->decimal('net_amount', 19, 6);
            $table->string('currency_code', 10)->default('USD');
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->json('destination_json')->nullable();
            $table->text('merchant_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('payout_reference', 120)->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'requested_at']);
        });

        Schema::create('merchant_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained('merchant_wallets')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('withdrawal_id')->nullable()->constrained('merchant_withdrawals')->nullOnDelete();
            $table->string('type', 60);
            $table->enum('direction', ['credit', 'debit']);
            $table->enum('status', ['pending', 'available', 'completed', 'reversed', 'failed'])->default('completed');
            $table->string('currency_code', 10)->default('USD');
            $table->decimal('gross_amount', 19, 6)->default(0);
            $table->decimal('fee_amount', 19, 6)->default(0);
            $table->decimal('amount', 19, 6)->default(0);
            $table->decimal('balance_after', 19, 6)->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
            $table->index(['order_id', 'type']);
            $table->index(['available_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_wallet_transactions');
        Schema::dropIfExists('merchant_withdrawals');
        Schema::dropIfExists('merchant_payout_methods');
        Schema::dropIfExists('merchant_wallets');
    }
};
