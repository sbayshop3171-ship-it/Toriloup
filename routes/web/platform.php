<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$platformHosts = array_values(array_filter(array_unique(array_merge(
    [(string) config('saas.owner_host')],
    (array) config('saas.owner_host_aliases', [])
))));

foreach ($platformHosts as $index => $platformHost) {
    Route::domain($platformHost)
        ->middleware(['installed', 'identifySurface', 'ensurePlatformHost'])
        ->group(function () use ($index) {
            $route = Route::get('/up', function (Request $request) {
                return response()->json([
                    'status' => true,
                    'surface' => $request->attributes->get(config('tenancy.surface_request_attribute', 'saas.surface')),
                    'scaffold' => 'platform-web',
                ]);
            });

            if ($index === 0) {
                $route->name('platform.web.up');
            }
        });
}
