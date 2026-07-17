<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\Saas\TenantProvisioningService;
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

if (!function_exists('opsResolveManifestPath')) {
    function opsResolveManifestPath(string $manifestPath): string
    {
        if (str_starts_with($manifestPath, DIRECTORY_SEPARATOR)) {
            return $manifestPath;
        }

        return base_path($manifestPath);
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

Artisan::command('ops:soft-launch-audit {--json}', function () {
    $summary = [
        'tenants_total' => Tenant::query()->count(),
        'tenants_active' => Tenant::query()->where('status', 'active')->count(),
        'tenants_draft' => Tenant::query()->where('status', 'draft')->count(),
        'tenants_suspended' => Tenant::query()->where('status', 'suspended')->count(),
        'tenants_live' => Tenant::query()->where('onboarding_status', 'live')->count(),
        'tenants_basic_complete' => Tenant::query()->where('onboarding_status', 'basic_complete')->count(),
        'custom_domains_pending' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'pending')->count(),
        'custom_domains_verified' => TenantDomain::query()->where('domain_type', 'custom')->where('verification_status', 'verified')->count(),
        'subscriptions_active' => TenantSubscription::query()->where('status', 'active')->count(),
    ];

    if ((bool) $this->option('json')) {
        $this->line(json_encode([
            'status' => true,
            'summary' => $summary,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        foreach ($summary as $key => $value) {
            $this->line("{$key}: {$value}");
        }
    }

    return 0;
})->purpose('Summarize current tenant, domain, and subscription readiness before controlled launch.');

Artisan::command('ops:soft-launch-onboard {manifest} {--dry-run} {--mark-live} {--json}', function (TenantProvisioningService $tenantProvisioningService) {
    $resolvedPath = opsResolveManifestPath((string) $this->argument('manifest'));

    if (!File::exists($resolvedPath)) {
        $this->error("Manifest not found: {$resolvedPath}");

        return 1;
    }

    $decoded = json_decode((string) File::get($resolvedPath), true);

    if (!is_array($decoded)) {
        $this->error('Manifest must decode to a JSON object or array.');

        return 1;
    }

    $defaults = is_array($decoded['defaults'] ?? null) ? $decoded['defaults'] : [];
    $merchants = $decoded['merchants'] ?? $decoded;

    if (!is_array($merchants) || $merchants === []) {
        $this->error('Manifest must contain a non-empty merchants array.');

        return 1;
    }

    $rows = [];
    $stats = [
        'created' => 0,
        'existing' => 0,
        'would_create' => 0,
        'failed' => 0,
    ];

    foreach (array_values($merchants) as $index => $merchant) {
        $entryNumber = $index + 1;

        if (!is_array($merchant)) {
            $rows[] = [
                'entry' => $entryNumber,
                'slug' => '-',
                'status' => 'failed',
                'detail' => 'Merchant entry must be an object.',
                'storefront' => '-',
            ];
            $stats['failed']++;
            continue;
        }

        $payload = array_merge([
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
            'plan_code' => 'starter',
        ], $defaults, $merchant);

        $errors = [];

        foreach (['owner_name', 'store_name', 'store_slug', 'password'] as $requiredField) {
            if (!filled($payload[$requiredField] ?? null)) {
                $errors[] = "{$requiredField} is required";
            }
        }

        if (!filled($payload['email'] ?? null) && !(filled($payload['phone'] ?? null) && filled($payload['country_code'] ?? null))) {
            $errors[] = 'email or phone + country_code is required';
        }

        if ($errors !== []) {
            $rows[] = [
                'entry' => $entryNumber,
                'slug' => (string) ($payload['store_slug'] ?? '-'),
                'status' => 'failed',
                'detail' => implode('; ', $errors),
                'storefront' => '-',
            ];
            $stats['failed']++;
            continue;
        }

        $existingTenant = Tenant::query()->where('slug', (string) $payload['store_slug'])->first();
        $existingUser = null;

        if (filled($payload['email'] ?? null)) {
            $existingUser = User::query()->where('email', (string) $payload['email'])->where('is_guest', 0)->first();
        } elseif (filled($payload['phone'] ?? null) && filled($payload['country_code'] ?? null)) {
            $existingUser = User::query()
                ->where('phone', (string) $payload['phone'])
                ->where('country_code', (string) $payload['country_code'])
                ->where('is_guest', 0)
                ->first();
        }

        if ($existingTenant !== null || $existingUser !== null) {
            $tenant = $existingTenant;

            if ($this->option('mark-live') && $tenant !== null) {
                $tenant->forceFill([
                    'status' => 'active',
                    'onboarding_status' => 'live',
                    'approved_at' => $tenant->approved_at ?? now(),
                    'launched_at' => $tenant->launched_at ?? now(),
                ])->save();
            }

            $rows[] = [
                'entry' => $entryNumber,
                'slug' => (string) ($payload['store_slug'] ?? '-'),
                'status' => 'existing',
                'detail' => $tenant !== null ? "tenant #{$tenant->id}" : 'matching merchant user already exists',
                'storefront' => $tenant !== null ? sprintf('https://%s.%s', $tenant->slug, config('saas.fallback_subdomain_suffix')) : '-',
            ];
            $stats['existing']++;
            continue;
        }

        if ((bool) $this->option('dry-run')) {
            $rows[] = [
                'entry' => $entryNumber,
                'slug' => (string) $payload['store_slug'],
                'status' => 'would_create',
                'detail' => sprintf('plan=%s owner=%s', (string) ($payload['plan_code'] ?? 'starter'), (string) $payload['owner_name']),
                'storefront' => sprintf('https://%s.%s', $payload['store_slug'], config('saas.fallback_subdomain_suffix')),
            ];
            $stats['would_create']++;
            continue;
        }

        try {
            $result = $tenantProvisioningService->registerMerchant($payload);
            $tenant = $result['tenant']->fresh();

            if ((bool) $this->option('mark-live')) {
                $tenant->forceFill([
                    'status' => 'active',
                    'onboarding_status' => 'live',
                    'approved_at' => $tenant->approved_at ?? now(),
                    'launched_at' => $tenant->launched_at ?? now(),
                ])->save();
            }

            $rows[] = [
                'entry' => $entryNumber,
                'slug' => $tenant->slug,
                'status' => 'created',
                'detail' => sprintf('tenant #%d status=%s onboarding=%s', $tenant->id, $tenant->status, $tenant->onboarding_status),
                'storefront' => 'https://'.$result['domain']->hostname,
            ];
            $stats['created']++;
        } catch (Throwable $throwable) {
            $rows[] = [
                'entry' => $entryNumber,
                'slug' => (string) $payload['store_slug'],
                'status' => 'failed',
                'detail' => $throwable->getMessage(),
                'storefront' => '-',
            ];
            $stats['failed']++;
        }
    }

    if ((bool) $this->option('json')) {
        $this->line(json_encode([
            'status' => $stats['failed'] === 0,
            'manifest' => $resolvedPath,
            'dry_run' => (bool) $this->option('dry-run'),
            'mark_live' => (bool) $this->option('mark-live'),
            'stats' => $stats,
            'results' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        $this->info('Soft launch onboarding manifest: '.$resolvedPath);
        $this->table(['Entry', 'Slug', 'Status', 'Detail', 'Storefront'], array_map(static fn (array $row): array => [
            $row['entry'],
            $row['slug'],
            $row['status'],
            $row['detail'],
            $row['storefront'],
        ], $rows));

        $this->line(sprintf(
            'Summary: created=%d existing=%d would_create=%d failed=%d',
            $stats['created'],
            $stats['existing'],
            $stats['would_create'],
            $stats['failed']
        ));
    }

    return $stats['failed'] === 0 ? 0 : 1;
})->purpose('Dry-run or provision a controlled cohort of merchants for soft launch from a JSON manifest.');

Schedule::command('ops:backup-audit --allow-missing --max-age-hours='.env('OPS_BACKUP_MAX_AGE_HOURS', 36))
    ->dailyAt('03:15');
