<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_demo_content_seeds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_type', 'source_id'], 'tenant_demo_seed_source_unique');
            $table->index(['target_type', 'target_id'], 'tenant_demo_seed_target_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_demo_content_seeds');
    }
};
