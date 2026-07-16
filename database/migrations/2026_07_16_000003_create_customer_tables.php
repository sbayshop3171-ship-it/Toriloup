<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country_code', 10)->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->boolean('is_guest')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone', 'country_code']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label', 60)->nullable();
            $table->string('recipient_name', 160);
            $table->string('recipient_phone', 30);
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
