<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Services\Saas\TenantSettingsService;
use Illuminate\Support\Facades\Storage;

class StorefrontBranding
{
    public function __construct(private readonly TenantSettingsService $tenantSettingsService)
    {
    }

    public function logoUrl(?Tenant $tenant): ?string
    {
        if ($tenant instanceof Tenant) {
            $settings = $this->tenantSettingsService->mergedForTenant($tenant);

            return $this->assetUrl($settings['theme_logo'] ?? null)
                ?: $this->assetUrl($settings['company_logo'] ?? null);
        }

        return $this->themeAsset('theme_logo', 'logo');
    }

    public function faviconUrl(?Tenant $tenant): string
    {
        if ($tenant instanceof Tenant) {
            $settings = $this->tenantSettingsService->mergedForTenant($tenant);
            $tenantIcon = $this->assetUrl($settings['theme_favicon_logo'] ?? null)
                ?: $this->assetUrl($settings['theme_logo'] ?? null)
                ?: $this->assetUrl($settings['company_logo'] ?? null);

            if ($tenantIcon !== null) {
                return $tenantIcon;
            }
        }

        return $this->themeAsset('theme_favicon_logo', 'faviconLogo')
            ?: asset('images/required/theme-favicon-logo.png');
    }

    private function assetUrl(mixed $path): ?string
    {
        if (!is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function themeAsset(string $key, string $attribute): ?string
    {
        return ThemeSetting::where(['key' => $key])->first()?->{$attribute};
    }
}
