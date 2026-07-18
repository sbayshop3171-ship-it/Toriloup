<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('group_key', 80);
            $table->string('setting_key', 120);
            $table->longText('setting_value')->nullable();
            $table->enum('value_type', ['string', 'integer', 'decimal', 'boolean', 'json', 'text'])->default('string');
            $table->boolean('is_encrypted')->default(false);
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'group_key', 'setting_key'], 'tenant_settings_unique_key');
        });

        Schema::create('tenant_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('feature_code', 80);
            $table->boolean('status')->default(false);
            $table->enum('source', ['platform_default', 'plan', 'owner_override', 'merchant_choice'])->default('platform_default');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_code']);
        });

        Schema::create('tenant_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider_code', 60);
            $table->string('display_name', 120);
            $table->boolean('status')->default(false);
            $table->string('checkout_label', 120)->nullable();
            $table->enum('fee_type', ['none', 'fixed', 'percent'])->default('none');
            $table->decimal('fee_value', 12, 4)->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('config_json')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'provider_code']);
        });

        Schema::create('tenant_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('channel_code', 60);
            $table->boolean('status')->default(false);
            $table->json('config_json')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'channel_code']);
        });

        Schema::create('tenant_theme_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('theme_code', 80);
            $table->string('theme_version', 30);
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('location_code', 60);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'location_code']);
        });

        Schema::create('tenant_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('tenant_navigation_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tenant_navigation_items')->nullOnDelete();
            $table->string('label', 120);
            $table->enum('target_type', ['page', 'collection', 'product', 'url']);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_url', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['menu_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_navigation_items');
        Schema::dropIfExists('tenant_navigation_menus');
        Schema::dropIfExists('tenant_theme_versions');
        Schema::dropIfExists('tenant_notification_channels');
        Schema::dropIfExists('tenant_payment_methods');
        Schema::dropIfExists('tenant_feature_flags');
        Schema::dropIfExists('tenant_settings');
    }
};
