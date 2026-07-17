<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProductionOperationsCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'saas.owner_host' => 'owner.company.com',
            'saas.merchant_host' => 'merchant.company.com',
            'saas.fallback_subdomain_suffix' => 'company.com',
        ]);

        File::ensureDirectoryExists(storage_path('logs'));
        File::ensureDirectoryExists(storage_path('app/public'));
        File::ensureDirectoryExists(base_path('bootstrap/cache'));

        if (!file_exists(public_path('storage'))) {
            $this->artisan('storage:link')->run();
        }
    }

    public function test_ops_deploy_health_command_passes_with_local_baseline(): void
    {
        $this
            ->artisan('ops:deploy-health')
            ->expectsOutputToContain('[PASS] app_key')
            ->expectsOutputToContain('[PASS] database_connection')
            ->expectsOutputToContain('[PASS] named_routes')
            ->assertExitCode(0);
    }

    public function test_ops_backup_audit_command_detects_recent_and_stale_backups(): void
    {
        $backupPath = storage_path('app/backups/testing');
        File::deleteDirectory($backupPath);
        File::ensureDirectoryExists($backupPath);

        $recentBackup = $backupPath.'/recent-backup.sql.gz';
        File::put($recentBackup, str_repeat('recent-backup', 16));
        touch($recentBackup, time());

        $this
            ->artisan('ops:backup-audit', ['--path' => $backupPath, '--max-age-hours' => 2])
            ->expectsOutputToContain('[PASS] recent_backup')
            ->assertExitCode(0);

        $staleBackup = $backupPath.'/stale-backup.sql.gz';
        File::put($staleBackup, 'stale');
        touch($staleBackup, time() - 72 * 3600);
        unlink($recentBackup);

        $this
            ->artisan('ops:backup-audit', ['--path' => $backupPath, '--max-age-hours' => 2])
            ->expectsOutputToContain('[FAIL] recent_backup')
            ->assertExitCode(1);
    }

    public function test_ops_smoke_command_passes_in_non_strict_mode(): void
    {
        $this
            ->artisan('ops:smoke')
            ->expectsOutputToContain('[PASS] workspace_hosts_are_distinct')
            ->expectsOutputToContain('[PASS] surface_routes_present')
            ->assertExitCode(0);
    }

    public function test_ops_backup_audit_rejects_tiny_backup_artifacts(): void
    {
        $backupPath = storage_path('app/backups/tiny');
        File::deleteDirectory($backupPath);
        File::ensureDirectoryExists($backupPath);

        $tinyBackup = $backupPath.'/empty-dump.sql.gz';
        File::put($tinyBackup, 'too-small');
        touch($tinyBackup, time());

        $this
            ->artisan('ops:backup-audit', ['--path' => $backupPath, '--max-age-hours' => 2])
            ->expectsOutputToContain('[FAIL] recent_backup')
            ->assertExitCode(1);
    }
}
