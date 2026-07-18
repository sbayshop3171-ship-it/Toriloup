<?php

namespace App\Http\Resources;


use App\Models\ThemeSetting;
use Illuminate\Http\Resources\Json\JsonResource;

class ThemeResource extends JsonResource
{

    public array $info;

    public function __construct($info)
    {
        parent::__construct($info);
        $this->info = $info;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            "theme_logo"         => $this->tenantImage('theme_logo') ?? $this->themeAsset('theme_logo', 'logo'),
            "theme_favicon_logo" => $this->tenantImage('theme_favicon_logo') ?? $this->themeAsset('theme_favicon_logo', 'faviconLogo'),
            "theme_footer_logo"  => $this->tenantImage('theme_footer_logo') ?? $this->themeAsset('theme_footer_logo', 'footerLogo'),
        ];
    }

    public function themeImage($key)
    {
        return ThemeSetting::where(['key' => $key])->first();
    }

    private function themeAsset(string $key, string $attribute): string
    {
        $themeSetting = $this->themeImage($key);

        return $themeSetting?->{$attribute} ?: asset(match ($key) {
            'theme_favicon_logo' => 'images/required/theme-favicon-logo.png',
            'theme_footer_logo' => 'images/required/theme-footer-logo.png',
            default => 'images/required/theme-logo.png',
        });
    }

    private function tenantImage(string $key): ?string
    {
        $path = is_array($this->info) ? ($this->info[$key] ?? null) : null;

        if (!is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.$path);
    }
}
