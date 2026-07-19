<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $tenantTables = [
        'sliders' => ['title', 'link', 'description', 'status', 'created_at', 'updated_at'],
        'pages' => ['title', 'slug', 'description', 'menu_section_id', 'menu_template_id', 'status', 'created_at', 'updated_at'],
        'benefits' => ['title', 'description', 'status', 'sort', 'created_at', 'updated_at'],
        'currencies' => ['name', 'symbol', 'code', 'is_cryptocurrency', 'exchange_rate', 'created_at', 'updated_at'],
        'taxes' => ['name', 'code', 'tax_rate', 'status', 'created_at', 'updated_at'],
        'outlets' => ['name', 'email', 'phone', 'country_code', 'latitude', 'longitude', 'city', 'state', 'zip_code', 'address', 'status', 'created_at', 'updated_at'],
    ];

    /**
     * @var array<int, string>
     */
    private array $sharedTemplateTables = [
        'sliders',
    ];

    public function up(): void
    {
        foreach (array_keys($this->tenantTables) as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        $this->copyGlobalDefaultsToExistingTenants();
        $this->addTenantColumnToRoles();
    }

    public function down(): void
    {
        $this->dropTenantColumnFromRoles();

        foreach (array_keys($this->tenantTables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $this->dropForeignIfExists($table, $tableName, 'tenant_id');
                $table->dropColumn('tenant_id');
            });
        }
    }

    private function copyGlobalDefaultsToExistingTenants(): void
    {
        if (!Schema::hasTable('tenants')) {
            return;
        }

        $tenantIds = DB::table('tenants')->pluck('id');

        if ($tenantIds->isEmpty()) {
            return;
        }

        foreach ($this->tenantTables as $tableName => $columns) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            if (in_array($tableName, $this->sharedTemplateTables, true)) {
                continue;
            }

            $availableColumns = array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn($tableName, $column)
            ));
            $globalRows = DB::table($tableName)->whereNull('tenant_id')->get();

            foreach ($tenantIds as $tenantId) {
                foreach ($globalRows as $globalRow) {
                    $attributes = ['tenant_id' => $tenantId];

                    foreach ($availableColumns as $column) {
                        $attributes[$column] = $globalRow->{$column};
                    }

                    $identity = $this->copyIdentity($tableName, $attributes);

                    $exists = DB::table($tableName)
                        ->where('tenant_id', $tenantId)
                        ->where($identity)
                        ->exists();

                    if (!$exists) {
                        DB::table($tableName)->insert($attributes);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function copyIdentity(string $tableName, array $attributes): array
    {
        return match ($tableName) {
            'pages' => ['slug' => $attributes['slug'] ?? null],
            'currencies' => ['code' => $attributes['code'] ?? null],
            'taxes' => ['code' => $attributes['code'] ?? null],
            default => isset($attributes['name'])
                ? ['name' => $attributes['name']]
                : ['title' => $attributes['title'] ?? null],
        };
    }

    private function addTenantColumnToRoles(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (!Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        try {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropUnique('roles_name_guard_name_unique');
            });
        } catch (Throwable) {
            // Fresh installs may already use the tenant-aware index from the base migration.
        }

        try {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_name_guard_unique');
            });
        } catch (Throwable) {
            // Avoid failing repeated deploys if the index already exists.
        }
    }

    private function dropTenantColumnFromRoles(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasColumn('roles', 'tenant_id')) {
            return;
        }

        try {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropUnique('roles_tenant_name_guard_unique');
            });
        } catch (Throwable) {
        }

        Schema::table('roles', function (Blueprint $table): void {
            $this->dropForeignIfExists($table, 'roles', 'tenant_id');
            $table->dropColumn('tenant_id');
        });

        try {
            Schema::table('roles', function (Blueprint $table): void {
                $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
            });
        } catch (Throwable) {
        }
    }

    private function dropForeignIfExists(Blueprint $table, string $tableName, string $column): void
    {
        try {
            $table->dropForeign([$column]);
        } catch (Throwable) {
            try {
                $table->dropForeign($tableName.'_'.$column.'_foreign');
            } catch (Throwable) {
            }
        }
    }
};
