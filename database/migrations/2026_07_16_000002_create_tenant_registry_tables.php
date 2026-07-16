<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 160);
            $table->string('legal_name', 190)->nullable();
            $table->string('slug', 120)->unique();
            $table->string('store_code', 40)->unique();
            $table->enum('status', ['draft', 'active', 'suspended', 'archived'])->default('draft');
            $table->string('plan_code', 60)->nullable();
            $table->enum('onboarding_status', ['pending', 'basic_complete', 'catalog_started', 'live'])->default('pending');
            $table->string('primary_locale', 10)->default('en');
            $table->string('primary_currency_code', 10)->default('USD');
            $table->string('timezone', 60)->default('UTC');
            $table->string('country_code', 10)->nullable();
            $table->string('contact_email', 190)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->unsignedBigInteger('logo_media_id')->nullable();
            $table->unsignedBigInteger('favicon_media_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
            $table->index('created_by_user_id');
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('hostname', 255)->unique();
            $table->enum('domain_type', ['subdomain', 'custom']);
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_fallback')->default(false);
            $table->enum('ssl_status', ['pending', 'active', 'failed'])->default('pending');
            $table->enum('verification_status', ['pending', 'verified', 'failed'])->default('pending');
            $table->string('dns_provider', 50)->nullable();
            $table->string('cloudflare_zone_id', 80)->nullable();
            $table->string('cloudflare_hostname_id', 120)->nullable();
            $table->string('verification_token', 120)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'domain_type']);
            $table->index(['tenant_id', 'is_primary']);
        });

        Schema::create('tenant_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->enum('status', ['invited', 'active', 'suspended'])->default('active');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('tenant_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email', 190);
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->string('invite_token', 120)->unique();
            $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])->default('pending');
            $table->timestamp('expires_at');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invites');
        Schema::dropIfExists('tenant_members');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
