<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Storage;

class StorefrontBranding
{
    public function logoUrl(?Tenant $tenant): ?string
    {
        if ($tenant instanceof Tenant) {
            $settings = $this->tenantAssets($tenant, ['company_logo', 'theme_logo']);

            return $this->assetUrl($settings['company_logo'] ?? null)
                ?: $this->assetUrl($settings['theme_logo'] ?? null);
        }

        return $this->themeAsset('theme_logo', 'logo');
    }

    public function faviconUrl(?Tenant $tenant): string
    {
        if ($tenant instanceof Tenant) {
            $settings = $this->tenantAssets($tenant, ['theme_favicon_logo', 'company_logo', 'theme_logo']);
            $tenantIcon = $this->assetUrl($settings['theme_favicon_logo'] ?? null)
                ?: $this->assetUrl($settings['company_logo'] ?? null)
                ?: $this->assetUrl($settings['theme_logo'] ?? null);

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

    /**
     * @param  array<int, string>  $keys
     * @return array<string, string|null>
     */
    private function tenantAssets(Tenant $tenant, array $keys): array
    {
        return TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('setting_key', $keys)
            ->pluck('setting_value', 'setting_key')
            ->all();
    }
}
