<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_domains', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenant_domains', 'dns_setup_mode')) {
                $table->string('dns_setup_mode', 30)->default('cname');
            }

            if (!Schema::hasColumn('tenant_domains', 'cloudflare_zone_status')) {
                $table->string('cloudflare_zone_status', 30)->nullable();
            }

            if (!Schema::hasColumn('tenant_domains', 'cloudflare_name_servers')) {
                $table->json('cloudflare_name_servers')->nullable();
            }

            if (!Schema::hasColumn('tenant_domains', 'cloudflare_dns_records')) {
                $table->json('cloudflare_dns_records')->nullable();
            }

            if (!Schema::hasColumn('tenant_domains', 'cloudflare_activated_at')) {
                $table->timestamp('cloudflare_activated_at')->nullable();
            }

            if (!Schema::hasColumn('tenant_domains', 'cloudflare_activation_checked_at')) {
                $table->timestamp('cloudflare_activation_checked_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('tenant_domains', 'dns_setup_mode') ? 'dns_setup_mode' : null,
            Schema::hasColumn('tenant_domains', 'cloudflare_zone_status') ? 'cloudflare_zone_status' : null,
            Schema::hasColumn('tenant_domains', 'cloudflare_name_servers') ? 'cloudflare_name_servers' : null,
            Schema::hasColumn('tenant_domains', 'cloudflare_dns_records') ? 'cloudflare_dns_records' : null,
            Schema::hasColumn('tenant_domains', 'cloudflare_activated_at') ? 'cloudflare_activated_at' : null,
            Schema::hasColumn('tenant_domains', 'cloudflare_activation_checked_at') ? 'cloudflare_activation_checked_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('tenant_domains', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
