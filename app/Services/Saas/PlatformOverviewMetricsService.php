<?php

namespace App\Services\Saas;

use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;

class PlatformOverviewMetricsService
{
    public function totalMerchants(): int
    {
        return Tenant::query()->count();
    }

    public function totalProducts(): int
    {
        return Product::query()->count();
    }

    public function totalRevenue(): float
    {
        return (float) Order::query()
            ->where('payment_status', PaymentStatus::PAID)
            ->sum('total');
    }

    public function totalCustomers(): int
    {
        $customerQuery = Customer::query();

        if (!$customerQuery->exists()) {
            return Order::query()
                ->whereNotNull('user_id')
                ->distinct()
                ->count('user_id');
        }

        $legacyLinkedCustomers = Customer::query()
            ->whereNotNull('legacy_user_id')
            ->distinct()
            ->count('legacy_user_id');

        $emailCustomers = Customer::query()
            ->whereNull('legacy_user_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['email'])
            ->map(fn (Customer $customer) => mb_strtolower(trim((string) $customer->email)))
            ->filter()
            ->unique()
            ->count();

        $phoneCustomers = Customer::query()
            ->whereNull('legacy_user_id')
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', '');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['country_code', 'phone'])
            ->map(fn (Customer $customer) => $this->normalizePhoneIdentity($customer))
            ->filter()
            ->unique()
            ->count();

        $anonymousCustomers = Customer::query()
            ->whereNull('legacy_user_id')
            ->where(function ($query): void {
                $query->whereNull('email')->orWhere('email', '');
            })
            ->where(function ($query): void {
                $query->whereNull('phone')->orWhere('phone', '');
            })
            ->count();

        return $legacyLinkedCustomers + $emailCustomers + $phoneCustomers + $anonymousCustomers;
    }

    private function normalizePhoneIdentity(Customer $customer): string
    {
        $countryCode = preg_replace('/\s+/', '', (string) $customer->country_code);
        $phone = preg_replace('/\D+/', '', (string) $customer->phone);

        return trim($countryCode.'|'.$phone, '|');
    }
}
