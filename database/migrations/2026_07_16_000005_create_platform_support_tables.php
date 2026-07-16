<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_providers', function (Blueprint $table) {
            $table->id();
            $table->enum('provider_type', ['payment', 'sms', 'mail', 'push', 'analytics', 'domain']);
            $table->string('provider_code', 60)->unique();
            $table->string('name', 120);
            $table->boolean('status')->default(true);
            $table->json('config_json');
            $table->timestamps();
            $table->index(['provider_type', 'status']);
        });

        Schema::create('platform_theme_presets', function (Blueprint $table) {
            $table->id();
            $table->string('theme_code', 80);
            $table->string('version', 30);
            $table->string('name', 120);
            $table->enum('status', ['draft', 'published', 'retired'])->default('draft');
            $table->json('schema_json');
            $table->json('assets_manifest_json')->nullable();
            $table->timestamps();
            $table->unique(['theme_code', 'version']);
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('actor_scope', ['platform', 'merchant', 'system']);
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('action_code', 120);
            $table->string('entity_type', 120);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values_json')->nullable();
            $table->json('new_values_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'action_code']);
        });

        Schema::create('domain_verification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_domain_id')->constrained('tenant_domains')->cascadeOnDelete();
            $table->enum('check_status', ['success', 'failed']);
            $table->enum('check_type', ['dns', 'ssl', 'hostname']);
            $table->text('message')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('checked_at')->useCurrent();
            $table->index(['tenant_domain_id', 'check_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_verification_logs');
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('platform_theme_presets');
        Schema::dropIfExists('platform_providers');
    }
};
