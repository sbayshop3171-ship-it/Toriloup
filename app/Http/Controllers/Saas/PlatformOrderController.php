<?php

namespace App\Http\Controllers\Saas;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Libraries\AppLibrary;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery($request);
        $summaryQuery = clone $query;
        $orderColumn = in_array($request->get('order_column'), ['id', 'order_datetime', 'total', 'status', 'payment_status'], true)
            ? $request->get('order_column')
            : 'id';
        $orderBy = strtolower((string) $request->get('order_by')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);

        $orders = $query
            ->orderBy($orderColumn, $orderBy)
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'summary' => $this->summary($summaryQuery),
            'data' => $orders->getCollection()
                ->map(fn (Order $order): array => $this->serializeOrder($order))
                ->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'from' => $orders->firstItem(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'to' => $orders->lastItem(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(int $orderId): JsonResponse
    {
        $order = Order::withoutGlobalScope('tenant')
            ->with(['tenant', 'user', 'paymentMethod', 'transaction', 'orderProducts', 'address', 'outletAddress'])
            ->findOrFail($orderId);

        return response()->json([
            'status' => true,
            'data' => $this->serializeOrder($order, true),
        ]);
    }

    private function baseQuery(Request $request): Builder
    {
        return Order::withoutGlobalScope('tenant')
            ->with(['tenant', 'user', 'paymentMethod', 'transaction'])
            ->when($request->filled('tenant_id'), fn (Builder $query) => $query->where('tenant_id', $request->integer('tenant_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', (int) $request->get('status')))
            ->when($request->filled('payment_status'), fn (Builder $query) => $query->where('payment_status', (int) $request->get('payment_status')))
            ->when($request->filled('from_date'), fn (Builder $query) => $query->whereDate('order_datetime', '>=', (string) $request->get('from_date')))
            ->when($request->filled('to_date'), fn (Builder $query) => $query->whereDate('order_datetime', '<=', (string) $request->get('to_date')))
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('q').'%';

                $query->where(function (Builder $searchQuery) use ($term): void {
                    $searchQuery
                        ->where('order_serial_no', 'like', $term)
                        ->orWhereHas('tenant', function (Builder $tenantQuery) use ($term): void {
                            $tenantQuery
                                ->where('name', 'like', $term)
                                ->orWhere('slug', 'like', $term)
                                ->orWhere('store_code', 'like', $term);
                        })
                        ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                            $userQuery
                                ->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term)
                                ->orWhere('phone', 'like', $term);
                        });
                });
            });
    }

    private function summary(Builder $query): array
    {
        $grossSales = (float) (clone $query)->sum('total');

        return [
            'total_orders' => (clone $query)->count(),
            'pending_orders' => (clone $query)->where('status', OrderStatus::PENDING)->count(),
            'paid_orders' => (clone $query)->where('payment_status', PaymentStatus::PAID)->count(),
            'gross_sales' => $grossSales,
            'gross_sales_display' => AppLibrary::currencyAmountFormat($grossSales),
        ];
    }

    private function serializeOrder(Order $order, bool $detail = false): array
    {
        $payload = [
            'id' => $order->id,
            'order_serial_no' => $order->order_serial_no,
            'tenant_id' => $order->tenant_id,
            'tenant' => $order->tenant?->only(['id', 'name', 'slug', 'store_code', 'status']),
            'customer' => $order->user?->only(['id', 'name', 'email', 'phone', 'country_code']),
            'total' => (float) $order->total,
            'total_display' => AppLibrary::currencyAmountFormat((float) $order->total),
            'payment_method' => $order->payment_method,
            'payment_method_name' => $order->paymentMethod?->name,
            'payment_status' => $order->payment_status,
            'payment_status_label' => $this->paymentStatusLabel((int) $order->payment_status),
            'status' => $order->status,
            'status_label' => $this->orderStatusLabel((int) $order->status),
            'source' => $order->source,
            'active' => (int) $order->active,
            'order_datetime' => $order->order_datetime?->toDateTimeString(),
            'created_at' => $order->created_at?->toDateTimeString(),
        ];

        if (!$detail) {
            return $payload;
        }

        $payload['transaction'] = $order->transaction?->only(['id', 'transaction_no', 'amount', 'payment_method', 'type', 'created_at']);
        $payload['order_products_count'] = $order->orderProducts->count();
        $payload['order_address_count'] = $order->address->count();

        return $payload;
    }

    private function orderStatusLabel(int $status): string
    {
        return match ($status) {
            OrderStatus::PENDING => 'Pending',
            OrderStatus::CONFIRMED => 'Confirmed',
            OrderStatus::ON_THE_WAY => 'On The Way',
            OrderStatus::DELIVERED => 'Delivered',
            OrderStatus::CANCELED => 'Canceled',
            OrderStatus::REJECTED => 'Rejected',
            default => 'Unknown',
        };
    }

    private function paymentStatusLabel(int $status): string
    {
        return match ($status) {
            PaymentStatus::PAID => 'Paid',
            PaymentStatus::UNPAID => 'Unpaid',
            default => 'Unknown',
        };
    }
}
