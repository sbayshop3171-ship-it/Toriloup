<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->enum('scope', ['platform', 'merchant']);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->enum('scope', ['platform', 'merchant']);
            $table->string('module', 80);
            $table->timestamps();
        });

        Schema::create('platform_role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('platform_permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id'], 'platform_role_permissions_primary');
        });

        Schema::create('user_platform_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platform_roles');
        Schema::dropIfExists('platform_role_permissions');
        Schema::dropIfExists('platform_permissions');
        Schema::dropIfExists('platform_roles');
    }
};
