<?php

namespace App\Http\Controllers\Saas;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PlatformCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::withoutGlobalScopes()
            ->with(['tenant', 'legacyUser'])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->string('q')).'%';

                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term)
                        );
                });
            })
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $this->groupCustomers($customers)->values(),
        ]);
    }

    public function show(string $customerId): JsonResponse
    {
        $masterKey = $this->decodeMasterKey($customerId);

        $customers = Customer::withoutGlobalScopes()
            ->with(['tenant', 'legacyUser'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Customer $customer) => $this->masterKey($customer) === $masterKey)
            ->values();

        if ($customers->isEmpty()) {
            abort(404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->serializeMasterCustomer($customers, true),
        ]);
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return Collection<int, array<string, mixed>>
     */
    private function groupCustomers(Collection $customers): Collection
    {
        return $customers
            ->groupBy(fn (Customer $customer) => $this->masterKey($customer))
            ->map(fn (Collection $group) => $this->serializeMasterCustomer($group))
            ->sortByDesc('last_activity_at')
            ->values();
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return array<string, mixed>
     */
    private function serializeMasterCustomer(Collection $customers, bool $detail = false): array
    {
        $ordersLookup = $this->ordersLookup($customers, $detail);
        $primary = $customers->first();
        $linkedMerchants = $customers->map(function (Customer $customer) use ($ordersLookup, $detail): array {
            $orderMetrics = $ordersLookup[$this->pairKey($customer)] ?? [
                'orders_count' => 0,
                'spend_total' => 0.0,
                'last_order_at' => null,
                'recent_orders' => [],
            ];

            return [
                'tenant_id' => $customer->tenant_id,
                'tenant_name' => $customer->tenant?->name,
                'tenant_slug' => $customer->tenant?->slug,
                'tenant_status' => $customer->tenant?->status,
                'storefront_hostname' => $customer->tenant?->slug.'.'.trim((string) config('saas.fallback_subdomain_suffix', 'toriloup.com'), '.'),
                'customer_id' => $customer->id,
                'status' => $customer->status,
                'orders_count' => $orderMetrics['orders_count'],
                'spend_total' => $orderMetrics['spend_total'],
                'last_order_at' => $orderMetrics['last_order_at'],
                'last_login_at' => optional($customer->last_login_at)?->toDateTimeString(),
                'recent_orders' => $detail ? $orderMetrics['recent_orders'] : [],
            ];
        })->sortByDesc(function (array $merchant): int|string|null {
            return $merchant['last_order_at'] ?? $merchant['last_login_at'];
        })->values();

        $lastActivityAt = collect($linkedMerchants)
            ->flatMap(fn (array $merchant) => [$merchant['last_order_at'], $merchant['last_login_at']])
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'id' => $this->encodeMasterKey($this->masterKey($primary)),
            'name' => $primary?->name ?: $primary?->legacyUser?->name,
            'email' => $this->normalizeEmail($primary?->email ?: $primary?->legacyUser?->email),
            'phone' => $primary?->phone ?: $primary?->legacyUser?->phone,
            'country_code' => $primary?->country_code ?: $primary?->legacyUser?->country_code,
            'legacy_user_id' => $customers->pluck('legacy_user_id')->filter()->first(),
            'registered_at' => optional($customers->sortBy('created_at')->first()?->created_at)?->toDateTimeString(),
            'linked_merchants_count' => $linkedMerchants->count(),
            'shadow_profiles_count' => $customers->count(),
            'total_orders' => $linkedMerchants->sum('orders_count'),
            'total_spend' => (float) $linkedMerchants->sum('spend_total'),
            'last_activity_at' => $lastActivityAt,
            'linked_merchants_preview' => $linkedMerchants
                ->take(3)
                ->map(fn (array $merchant) => $merchant['tenant_name'] ?: $merchant['tenant_slug'])
                ->filter()
                ->implode(', '),
            'linked_merchants' => $linkedMerchants,
        ];
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return array<string, array<string, mixed>>
     */
    private function ordersLookup(Collection $customers, bool $detail = false): array
    {
        $tenantIds = $customers->pluck('tenant_id')->unique()->values()->all();
        $legacyUserIds = $customers->pluck('legacy_user_id')->filter()->unique()->values()->all();

        if ($tenantIds === [] || $legacyUserIds === []) {
            return [];
        }

        $lookup = [];
        $orderRows = Order::withoutGlobalScopes()
            ->selectRaw(
                'tenant_id, user_id, COUNT(*) AS orders_count, '.
                'SUM(CASE WHEN payment_status = ? THEN total ELSE 0 END) AS spend_total, '.
                'MAX(order_datetime) AS last_order_at',
                [PaymentStatus::PAID]
            )
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('user_id', $legacyUserIds)
            ->groupBy('tenant_id', 'user_id')
            ->get();

        foreach ($orderRows as $row) {
            $lookup[(int) $row->tenant_id.':'.(int) $row->user_id] = [
                'orders_count' => (int) $row->orders_count,
                'spend_total' => (float) $row->spend_total,
                'last_order_at' => optional($row->last_order_at)?->toDateTimeString() ?? (string) $row->last_order_at,
                'recent_orders' => [],
            ];
        }

        if ($detail) {
            Order::withoutGlobalScopes()
                ->select(['id', 'tenant_id', 'user_id', 'order_serial_no', 'total', 'status', 'payment_status', 'order_datetime'])
                ->whereIn('tenant_id', $tenantIds)
                ->whereIn('user_id', $legacyUserIds)
                ->latest('order_datetime')
                ->get()
                ->groupBy(fn (Order $order) => $order->tenant_id.':'.$order->user_id)
                ->each(function (Collection $orders, string $pairKey) use (&$lookup): void {
                    $lookup[$pairKey]['recent_orders'] = $orders
                        ->take(5)
                        ->map(fn (Order $order) => [
                            'id' => $order->id,
                            'order_serial_no' => $order->order_serial_no,
                            'total' => (float) $order->total,
                            'status' => $order->status,
                            'payment_status' => $order->payment_status,
                            'order_datetime' => optional($order->order_datetime)?->toDateTimeString(),
                        ])
                        ->values()
                        ->all();
                });
        }

        return $lookup;
    }

    private function masterKey(Customer $customer): string
    {
        if ($customer->legacy_user_id) {
            return 'legacy:'.$customer->legacy_user_id;
        }

        if (filled($customer->email)) {
            return 'email:'.strtolower(trim((string) $customer->email));
        }

        if (filled($customer->phone)) {
            return 'phone:'.preg_replace('/\s+/', '', (string) $customer->country_code.$customer->phone);
        }

        return 'shadow:'.$customer->id;
    }

    private function pairKey(Customer $customer): string
    {
        return $customer->tenant_id.':'.$customer->legacy_user_id;
    }

    private function encodeMasterKey(string $masterKey): string
    {
        return rtrim(strtr(base64_encode($masterKey), '+/', '-_'), '=');
    }

    private function decodeMasterKey(string $encoded): string
    {
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false || $decoded === '') {
            throw ValidationException::withMessages([
                'customer' => 'Invalid customer identifier.',
            ]);
        }

        return $decoded;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!filled($email)) {
            return null;
        }

        return mb_strtolower(trim($email));
    }
}
