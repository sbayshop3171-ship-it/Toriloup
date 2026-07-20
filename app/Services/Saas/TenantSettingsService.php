<?php

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\SettingService;

class TenantSettingsService
{
    public function __construct(private readonly SettingService $settingService)
    {
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function seedDefaultsForTenant(Tenant $tenant, array $overrides = []): void
    {
        $settings = array_merge($this->defaultSettings(), $this->settingService->list(), $overrides);

        foreach ($settings as $settingKey => $settingValue) {
            TenantSetting::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'group_key' => $this->inferGroupKey($settingKey),
                    'setting_key' => $settingKey,
                ],
                [
                    'setting_value' => $this->serializeValue($settingValue),
                    'value_type' => $this->inferValueType($settingValue),
                    'is_encrypted' => false,
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedForTenant(?Tenant $tenant): array
    {
        $settings = array_merge($this->defaultSettings(), $this->settingService->list());

        if ($tenant === null) {
            return $settings;
        }

        $tenantSettings = TenantSetting::query()
            ->where('tenant_id', $tenant->id)
            ->get()
            ->mapWithKeys(fn (TenantSetting $tenantSetting) => [
                $tenantSetting->setting_key => $this->castValue($tenantSetting->setting_value, $tenantSetting->value_type),
            ])
            ->all();

        return array_merge($settings, $tenantSettings);
    }

    /**
     * @return array<string, mixed>
     */
    public function groupForTenant(Tenant $tenant, string $groupKey): array
    {
        return array_filter(
            $this->mergedForTenant($tenant),
            fn (mixed $value, string $settingKey): bool => $this->inferGroupKey($settingKey) === $groupKey,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function syncForTenant(Tenant $tenant, array $settings, ?User $actor = null): array
    {
        $existing = $this->mergedForTenant($tenant);
        $merged = array_merge($existing, $settings);

        foreach ($settings as $settingKey => $settingValue) {
            TenantSetting::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'group_key' => $this->inferGroupKey($settingKey),
                    'setting_key' => $settingKey,
                ],
                [
                    'setting_value' => $this->serializeValue($settingValue),
                    'value_type' => $this->inferValueType($settingValue),
                    'is_encrypted' => false,
                    'updated_by_user_id' => $actor?->id,
                ]
            );
        }

        return array_merge($merged, $settings);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function syncGroupForTenant(Tenant $tenant, string $groupKey, array $settings, ?User $actor = null): array
    {
        foreach ($settings as $settingKey => $settingValue) {
            TenantSetting::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'group_key' => $groupKey,
                    'setting_key' => $settingKey,
                ],
                [
                    'setting_value' => $this->serializeValue($settingValue),
                    'value_type' => $this->inferValueType($settingValue),
                    'is_encrypted' => false,
                    'updated_by_user_id' => $actor?->id,
                ]
            );
        }

        return $this->groupForTenant($tenant, $groupKey);
    }

    private function inferGroupKey(string $settingKey): string
    {
        foreach (['company', 'site', 'shipping_setup', 'theme', 'otp', 'social_media', 'notification', 'cookies'] as $groupKey) {
            if (str_starts_with($settingKey, $groupKey.'_')) {
                return $groupKey;
            }
        }

        return 'tenant';
    }

    private function inferValueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'decimal',
            is_array($value) => 'json',
            $value === null => 'text',
            default => 'string',
        };
    }

    private function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private function castValue(?string $value, string $valueType): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($valueType) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(): array
    {
        return [
            'company_name' => 'Toriloup Store',
            'company_email' => null,
            'company_calling_code' => '+880',
            'company_phone' => null,
            'company_website' => null,
            'company_city' => null,
            'company_state' => null,
            'company_country_code' => 'BD',
            'company_zip_code' => null,
            'company_latitude' => null,
            'company_longitude' => null,
            'company_address' => null,
            'company_logo' => null,
            'site_date_format' => 'Y-m-d',
            'site_time_format' => 'H:i',
            'site_default_timezone' => 'UTC',
            'site_default_currency' => 1,
            'site_default_currency_code' => 'USD',
            'site_default_language' => 1,
            'site_app_debug' => 10,
            'site_auto_update' => 10,
            'site_auto_visitor_currency' => 5,
            'site_android_app_link' => null,
            'site_ios_app_link' => null,
            'site_copyright' => 'Toriloup',
            'site_currency_position' => 5,
            'site_digit_after_decimal_point' => '2',
            'site_default_currency_symbol' => '$',
            'site_phone_verification' => 5,
            'site_email_verification' => 5,
            'site_language_switch' => 5,
            'site_online_payment_gateway' => 5,
            'site_default_sms_gateway' => null,
            'site_cash_on_delivery' => 5,
            'site_non_purchase_product_maximum_quantity' => 10,
            'site_is_return_product_price_add_to_credit' => 5,
            'shipping_setup_method' => 5,
            'shipping_setup_flat_rate_wise_cost' => '0',
            'shipping_setup_area_wise_default_cost' => '0',
            'otp_type' => 'both',
            'otp_digit_limit' => '6',
            'otp_expire_time' => '5',
            'social_media_facebook' => null,
            'social_media_instagram' => null,
            'social_media_twitter' => null,
            'social_media_youtube' => null,
            'cookies_details_page_id' => null,
            'cookies_summary' => null,
            'notification_fcm_api_key' => null,
            'notification_fcm_auth_domain' => null,
            'notification_fcm_project_id' => null,
            'notification_fcm_storage_bucket' => null,
            'notification_fcm_messaging_sender_id' => null,
            'notification_fcm_app_id' => null,
            'notification_fcm_measurement_id' => null,
            'notification_fcm_public_vapid_key' => null,
        ];
    }
}
