<?php

namespace App\Services\Saas;

use App\Enums\Activity;
use App\Models\PaymentGateway;
use App\Models\Tenant;
use App\Models\TenantPaymentMethod;
use Illuminate\Support\Collection;

class TenantPaymentMethodCatalogService
{
    private const CASH_GATEWAY_SLUG = 'cashondelivery';
    private const CASH_PROVIDER_ALIASES = ['cashondelivery', 'cash_on_delivery', 'cod'];

    /**
     * @return Collection<int, TenantPaymentMethod>
     */
    public function syncAvailableForTenant(Tenant $tenant): Collection
    {
        $gateways = $this->ownerApprovedGateways();
        $methods = TenantPaymentMethod::query()
            ->where('tenant_id', $tenant->id)
            ->get();
        $availableIds = [];

        foreach ($gateways as $gateway) {
            $method = $this->methodForGateway($methods, $gateway);

            if ($method === null) {
                $method = new TenantPaymentMethod([
                    'tenant_id' => $tenant->id,
                    'provider_code' => $gateway->slug,
                    'status' => $this->defaultStatusForGateway($gateway),
                    'fee_type' => 'none',
                    'fee_value' => null,
                    'sort_order' => $gateway->id,
                ]);
            }

            $method->forceFill([
                'display_name' => $method->display_name ?: $gateway->name,
                'checkout_label' => $method->checkout_label ?: $this->defaultCheckoutLabel($gateway),
                'config_json' => array_merge($method->config_json ?? [], [
                    'gateway_id' => $gateway->id,
                    'gateway_slug' => $gateway->slug,
                    'managed_by' => 'owner',
                    'owner_status' => $gateway->status,
                ]),
            ])->save();

            $availableIds[] = $method->id;
            $methods->push($method);
        }

        return TenantPaymentMethod::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $availableIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TenantPaymentMethod>
     */
    public function activeMethodsForTenant(Tenant $tenant): Collection
    {
        return $this->syncAvailableForTenant($tenant)
            ->filter(fn (TenantPaymentMethod $method): bool => $method->status)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function activeGatewaySlugsForTenant(Tenant $tenant): array
    {
        return $this->activeMethodsForTenant($tenant)
            ->map(fn (TenantPaymentMethod $method): string => $this->gatewaySlugForProviderCode($method->provider_code))
            ->unique()
            ->values()
            ->all();
    }

    public function gatewaySlugForProviderCode(string $providerCode): string
    {
        $providerCode = strtolower(trim($providerCode));

        return in_array($providerCode, self::CASH_PROVIDER_ALIASES, true)
            ? self::CASH_GATEWAY_SLUG
            : $providerCode;
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    public function ownerApprovedGateways(): Collection
    {
        return PaymentGateway::query()
            ->with('media')
            ->where('status', Activity::ENABLE)
            ->orderBy('id')
            ->get();
    }

    private function methodForGateway(Collection $methods, PaymentGateway $gateway): ?TenantPaymentMethod
    {
        $aliases = $gateway->slug === self::CASH_GATEWAY_SLUG
            ? self::CASH_PROVIDER_ALIASES
            : [$gateway->slug];

        return $methods->first(function (TenantPaymentMethod $method) use ($aliases): bool {
            return in_array(strtolower((string) $method->provider_code), $aliases, true);
        });
    }

    private function defaultStatusForGateway(PaymentGateway $gateway): bool
    {
        return $gateway->slug === self::CASH_GATEWAY_SLUG;
    }

    private function defaultCheckoutLabel(PaymentGateway $gateway): string
    {
        return $gateway->slug === self::CASH_GATEWAY_SLUG
            ? 'Pay with cash on delivery'
            : 'Pay with '.$gateway->name;
    }
}
