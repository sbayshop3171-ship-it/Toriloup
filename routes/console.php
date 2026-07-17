<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;

if (!function_exists('opsCheck')) {
    /**
     * @return array{name: string, ok: bool, detail: string}
     */
    function opsCheck(string $name, callable $callback): array
    {
        try {
            $result = $callback();

            if (is_array($result)) {
                return [
                    'name' => $name,
                    'ok' => (bool) ($result['ok'] ?? false),
                    'detail' => (string) ($result['detail'] ?? ''),
                ];
            }

            return [
                'name' => $name,
                'ok' => (bool) $result,
                'detail' => (bool) $result ? 'ok' : 'failed',
            ];
        } catch (Throwable $throwable) {
            return [
                'name' => $name,
                'ok' => false,
                'detail' => $throwable->getMessage(),
            ];
        }
    }
}

if (!function_exists('opsRenderChecks')) {
    /**
     * @param  array<int, array{name: string, ok: bool, detail: string}>  $checks
     */
    function opsRenderChecks($command, array $checks, bool $asJson = false): int
    {
        $failed = array_values(array_filter($checks, static fn (array $check): bool => $check['ok'] === false));

        if ($asJson) {
            $command->line(json_encode([
                'status' => count($failed) === 0,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($checks as $check) {
                $status = $check['ok'] ? 'PASS' : 'FAIL';
                $detail = $check['detail'] !== '' ? " ({$check['detail']})" : '';

                $command->line("[{$status}] {$check['name']}{$detail}");
            }
        }

        return count($failed) === 0 ? 0 : 1;
    }
}

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ops:deploy-health {--json}', function () {
    $publicStorage = public_path('storage');
    $storageTarget = storage_path('app/public');
    $backupPath = env('OPS_BACKUP_PATH', storage_path('app/backups'));

    $checks = [
        opsCheck('app_key', fn (): array => [
            'ok' => filled(config('app.key')),
            'detail' => filled(config('app.key')) ? 'configured' : 'missing',
        ]),
        opsCheck('database_connection', function (): array {
            DB::connection()->select('select 1');

            return [
                'ok' => true,
                'detail' => config('database.default'),
            ];
        }),
        opsCheck('storage_public_directory', fn (): array => [
            'ok' => File::isDirectory(storage_path('app/public')) && is_writable(storage_path('app/public')),
            'detail' => storage_path('app/public'),
        ]),
        opsCheck('public_storage_link', fn (): array => [
            'ok' => (is_link($publicStorage) || File::exists($publicStorage))
                && realpath($publicStorage) === realpath($storageTarget),
            'detail' => $publicStorage,
        ]),
        opsCheck('bootstrap_cache_writable', fn (): array => [
            'ok' => File::isDirectory(base_path('bootstrap/cache')) && is_writable(base_path('bootstrap/cache')),
            'detail' => base_path('bootstrap/cache'),
        ]),
        opsCheck('log_directory_writable', fn (): array => [
            'ok' => File::isDirectory(storage_path('logs')) && is_writable(storage_path('logs')),
            'detail' => storage_path('logs'),
        ]),
        opsCheck('queue_driver', fn (): array => [
            'ok' => app()->environment('production') ? config('queue.default') !== 'sync' : true,
            'detail' => config('queue.default'),
        ]),
        opsCheck('cache_store', fn (): array => [
            'ok' => app()->environment('production') ? config('cache.default') !== 'array' : true,
            'detail' => config('cache.default'),
        ]),
        opsCheck('saas_hosts', fn (): array => [
            'ok' => filled(config('saas.owner_host')) && filled(config('saas.merchant_host')) && filled(config('saas.fallback_subdomain_suffix')),
            'detail' => implode(', ', array_filter([
                config('saas.owner_host'),
                config('saas.merchant_host'),
                config('saas.fallback_subdomain_suffix'),
            ])),
        ]),
        opsCheck('named_routes', fn (): array => [
            'ok' => Route::has('payment.successful') && Route::has('storefront.up') && Route::has('platform.up') && Route::has('merchant.up'),
            'detail' => 'payment.successful + platform/merchant/storefront up',
        ]),
        opsCheck('backup_directory_present', fn (): array => [
            'ok' => File::isDirectory($backupPath) || !app()->environment('production'),
            'detail' => $backupPath,
        ]),
    ];

    return opsRenderChecks($this, $checks, (bool) $this->option('json'));
})->purpose('Validate production-critical dependencies before or after deploys.');

Artisan::command('ops:backup-audit {--path=} {--max-age-hours=36} {--allow-missing} {--json}', function () {
    $path = (string) ($this->option('path') ?: env('OPS_BACKUP_PATH', storage_path('app/backups')));
    $maxAgeHours = max((int) $this->option('max-age-hours'), 1);
    $allowMissing = (bool) $this->option('allow-missing');

    $checks = [
        opsCheck('backup_directory', function () use ($path, $allowMissing): array {
            if (!File::isDirectory($path)) {
                return [
                    'ok' => $allowMissing,
                    'detail' => $allowMissing ? 'missing but allowed' : 'missing',
                ];
            }

            return [
                'ok' => true,
                'detail' => $path,
            ];
        }),
        opsCheck('recent_backup', function () use ($allowMissing, $maxAgeHours, $path): array {
            if (!File::isDirectory($path)) {
                return [
                    'ok' => $allowMissing,
                    'detail' => $allowMissing ? 'skipped' : 'missing directory',
                ];
            }

            $files = collect(File::files($path))
                ->sortByDesc(static fn (\SplFileInfo $file): int => $file->getMTime())
                ->values();

            if ($files->isEmpty()) {
                return [
                    'ok' => $allowMissing,
                    'detail' => $allowMissing ? 'no backups yet' : 'no backup files found',
                ];
            }

            /** @var \SplFileInfo $latest */
            $latest = $files->first();
            $ageSeconds = max(time() - $latest->getMTime(), 0);
            $ageHours = round($ageSeconds / 3600, 2);

            return [
                'ok' => $ageHours <= $maxAgeHours,
                'detail' => "{$latest->getFilename()} age={$ageHours}h",
            ];
        }),
    ];

    return opsRenderChecks($this, $checks, (bool) $this->option('json'));
})->purpose('Validate that deploy backups exist and are still fresh.');

Artisan::command('ops:smoke {--strict} {--json}', function () {
    $strict = (bool) $this->option('strict');

    $checks = [
        opsCheck('workspace_hosts_are_distinct', fn (): array => [
            'ok' => config('saas.owner_host') !== config('saas.merchant_host'),
            'detail' => config('saas.owner_host').' / '.config('saas.merchant_host'),
        ]),
        opsCheck('surface_routes_present', fn (): array => [
            'ok' => Route::has('storefront.up') && Route::has('merchant.up') && Route::has('platform.up'),
            'detail' => 'surface health routes available',
        ]),
        opsCheck('payment_redirect_routes_present', fn (): array => [
            'ok' => Route::has('payment.success') && Route::has('payment.successful'),
            'detail' => 'payment success pipeline available',
        ]),
        opsCheck('app_debug_state', fn (): array => [
            'ok' => $strict ? config('app.debug') === false : true,
            'detail' => config('app.debug') ? 'debug-on' : 'debug-off',
        ]),
        opsCheck('session_driver', fn (): array => [
            'ok' => $strict ? config('session.driver') !== 'array' : true,
            'detail' => config('session.driver'),
        ]),
        opsCheck('queue_driver_runtime', fn (): array => [
            'ok' => $strict ? config('queue.default') !== 'sync' : true,
            'detail' => config('queue.default'),
        ]),
        opsCheck('schedule_list_accessible', function (): array {
            Artisan::call('schedule:list');

            return [
                'ok' => true,
                'detail' => 'schedule:list ok',
            ];
        }),
    ];

    return opsRenderChecks($this, $checks, (bool) $this->option('json'));
})->purpose('Run production-oriented smoke checks without needing a browser session.');

Schedule::command('ops:backup-audit --allow-missing --max-age-hours='.env('OPS_BACKUP_MAX_AGE_HOURS', 36))
    ->dailyAt('03:15');
