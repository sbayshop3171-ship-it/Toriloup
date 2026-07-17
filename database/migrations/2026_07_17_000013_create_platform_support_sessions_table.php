<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('impersonated_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_member_id')->nullable()->constrained('tenant_members')->nullOnDelete();
            $table->enum('status', ['pending', 'active', 'ended', 'expired'])->default('pending');
            $table->string('handoff_code', 100)->unique();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('merchant_token_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['owner_user_id', 'status']);
            $table->index(['merchant_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_support_sessions');
    }
};
