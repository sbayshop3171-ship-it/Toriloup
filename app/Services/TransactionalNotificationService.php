<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnOrderStatus;
use App\Enums\Role;
use App\Enums\SwitchBox;
use App\Models\NotificationAlert;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\ReturnAndRefund;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\MerchantNotificationPreferenceService;
use App\Services\PushDeviceTokenService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionalNotificationService
{
    public function __construct(
        private readonly FirebaseService $firebaseService,
        private readonly MerchantNotificationPreferenceService $merchantPreferences,
        private readonly PushDeviceTokenService $deviceTokens,
    )
    {
    }

    public function sendOrderStatusToCustomer(Order $order, int $status): ?UserNotification
    {
        $config = $this->orderStatusConfig($status);

        if ($config === null) {
            return null;
        }

        $order->loadMissing('user');

        return $this->sendToUser($order->user, 'customer', $config['event_key'], $config['title'], $config['body'], [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'topic' => 'order-notification',
            'data' => $this->orderData($order),
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendNewOrderToMerchant(Order $order): void
    {
        $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', 'admin_and_manager_new_order_message', 'New order received', 'New order #{order_serial_no} was placed.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'route_url' => 'online-orders',
            'topic' => 'new-order-found',
            'data' => $this->orderData($order) + ['route_url' => 'online-orders'],
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendCustomerOrderCanceledToMerchant(Order $order): void
    {
        $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', 'merchant_order_canceled_message', 'Order canceled', 'Order #{order_serial_no} was canceled by the customer.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'route_url' => 'online-orders',
            'topic' => 'order-canceled',
            'data' => $this->orderData($order) + ['route_url' => 'online-orders'],
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendPaymentSuccessful(Order $order): void
    {
        $order->loadMissing('user');

        $this->sendToUser($order->user, 'customer', 'payment_successful_message', 'Payment successful', 'Payment for order #{order_serial_no} was successful.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'topic' => 'payment-successful',
            'data' => $this->orderData($order) + ['payment_status' => (string) PaymentStatus::PAID],
            'context' => $this->orderContext($order),
        ]);

        $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', 'merchant_payment_received_message', 'Payment received', 'Payment received for order #{order_serial_no}.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'route_url' => 'online-orders',
            'topic' => 'payment-received',
            'data' => $this->orderData($order) + ['payment_status' => (string) PaymentStatus::PAID, 'route_url' => 'online-orders'],
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendPaymentFailed(Order $order): void
    {
        $order->loadMissing('user');

        $this->sendToUser($order->user, 'customer', 'payment_failed_message', 'Payment failed', 'Payment for order #{order_serial_no} could not be completed.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'topic' => 'payment-failed',
            'data' => $this->orderData($order) + ['payment_status' => (string) PaymentStatus::UNPAID],
            'context' => $this->orderContext($order),
        ]);

        $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', 'merchant_payment_failed_message', 'Payment failed', 'Payment failed for order #{order_serial_no}.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'route_url' => 'online-orders',
            'topic' => 'payment-failed',
            'data' => $this->orderData($order) + ['payment_status' => (string) PaymentStatus::UNPAID, 'route_url' => 'online-orders'],
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendReturnRequested(ReturnAndRefund $returnAndRefund): void
    {
        $returnAndRefund->loadMissing('order', 'user');
        $order = $returnAndRefund->order;

        if (!$order instanceof Order) {
            return;
        }

        $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', 'return_requested_message', 'Return/refund requested', 'Return/refund requested for order #{order_serial_no}.', [
            'tenant_id' => $order->tenant_id,
            'target_type' => 'return_order',
            'target_value' => (string) $returnAndRefund->id,
            'route_url' => 'return-and-refunds',
            'topic' => 'return-request',
            'data' => $this->returnData($returnAndRefund, $order) + ['route_url' => 'return-and-refunds'],
            'context' => $this->orderContext($order),
        ]);
    }

    public function sendReturnStatus(ReturnAndRefund $returnAndRefund, int $status): void
    {
        $returnAndRefund->loadMissing('order', 'user');
        $order = $returnAndRefund->order;

        if (!$order instanceof Order) {
            return;
        }

        $customerConfig = match ($status) {
            ReturnOrderStatus::ACCEPT => [
                'event_key' => 'return_approved_message',
                'title' => 'Return/refund approved',
                'body' => 'Your return/refund request for order #{order_serial_no} was approved.',
                'topic' => 'return-approved',
            ],
            ReturnOrderStatus::REJECTED => [
                'event_key' => 'return_rejected_message',
                'title' => 'Return/refund rejected',
                'body' => 'Your return/refund request for order #{order_serial_no} was rejected.',
                'topic' => 'return-rejected',
            ],
            default => null,
        };

        if ($customerConfig !== null) {
            $this->sendToUser($returnAndRefund->user, 'customer', $customerConfig['event_key'], $customerConfig['title'], $customerConfig['body'], [
                'tenant_id' => $order->tenant_id,
                'target_type' => 'return_order',
                'target_value' => (string) $returnAndRefund->id,
                'topic' => $customerConfig['topic'],
                'data' => $this->returnData($returnAndRefund, $order),
                'context' => $this->orderContext($order),
            ]);
        }

        $merchantConfig = match ($status) {
            ReturnOrderStatus::ACCEPT => [
                'event_key' => 'merchant_return_approved_message',
                'title' => 'Return/refund approved',
                'body' => 'Return/refund for order #{order_serial_no} was approved.',
                'topic' => 'return-approved',
            ],
            ReturnOrderStatus::REJECTED => [
                'event_key' => 'merchant_return_rejected_message',
                'title' => 'Return/refund rejected',
                'body' => 'Return/refund for order #{order_serial_no} was rejected.',
                'topic' => 'return-rejected',
            ],
            default => null,
        };

        if ($merchantConfig !== null) {
            $this->sendToUsers($this->merchantUsersForOrder($order), 'merchant', $merchantConfig['event_key'], $merchantConfig['title'], $merchantConfig['body'], [
                'tenant_id' => $order->tenant_id,
                'target_type' => 'return_order',
                'target_value' => (string) $returnAndRefund->id,
                'route_url' => 'return-and-refunds',
                'topic' => $merchantConfig['topic'],
                'data' => $this->returnData($returnAndRefund, $order) + ['route_url' => 'return-and-refunds'],
                'context' => $this->orderContext($order),
            ]);
        }
    }

    public function sendProductReview(ProductReview $review): void
    {
        $review->loadMissing('product', 'user');
        $tenantId = $review->tenant_id ?: $review->product?->tenant_id;

        $this->sendToUsers($this->merchantUsersForTenant($tenantId), 'merchant', 'merchant_new_review_message', 'New review received', 'A customer left a new review.', [
            'tenant_id' => $tenantId,
            'target_type' => 'review',
            'target_value' => (string) $review->id,
            'route_url' => 'reviews',
            'topic' => 'product-review',
            'data' => [
                'review_id' => (string) $review->id,
                'product_id' => (string) $review->product_id,
                'target_id' => (string) $review->id,
                'route_url' => 'reviews',
            ],
        ]);
    }

    public function sendMerchantRegistered(Tenant $tenant): void
    {
        $this->sendToUsers($this->platformOwnerUsers(), 'owner', 'platform_merchant_registered_message', 'New merchant registered', "{$tenant->name} registered a store.", [
            'tenant_id' => $tenant->id,
            'target_type' => 'merchant',
            'target_value' => (string) $tenant->id,
            'route_url' => 'merchants',
            'topic' => 'merchant-registered',
            'data' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
                'route_url' => 'merchants',
            ],
        ]);
    }

    public function sendToUser(?User $user, string $audience, string $eventKey, string $title, string $body, array $options = []): ?UserNotification
    {
        if (!$user instanceof User) {
            return null;
        }

        $alert = $this->alert($eventKey);

        // The Owner switch is the master gate. OFF suppresses both the inbox
        // record and push for every audience.
        if (!$this->pushEnabled($alert)) {
            return null;
        }

        if ($audience === 'merchant' && !empty($options['tenant_id'])) {
            $tenant = Tenant::query()->find($options['tenant_id']);
            if ($tenant instanceof Tenant && !$this->merchantPreferences->enabledFor($tenant, $user, $eventKey)) {
                return null;
            }
        }

        $message = $this->message($alert, $body, $options['context'] ?? []);

        $notification = UserNotification::query()->create([
            'tenant_id' => $options['tenant_id'] ?? null,
            'user_id' => $user->id,
            'audience' => $audience,
            'event_key' => $eventKey,
            'title' => $title,
            'body' => $message,
            'target_type' => $options['target_type'] ?? 'home',
            'target_value' => $options['target_value'] ?? null,
            'route_url' => $options['route_url'] ?? null,
            'data' => $options['data'] ?? [],
        ]);

        $this->push($notification, $user, $options['topic'] ?? $eventKey);

        return $notification;
    }

    public function sendToUsers(Collection|array $users, string $audience, string $eventKey, string $title, string $body, array $options = []): void
    {
        collect($users)
            ->filter(fn ($user): bool => $user instanceof User)
            ->unique('id')
            ->each(fn (User $user) => $this->sendToUser($user, $audience, $eventKey, $title, $body, $options));
    }

    public function merchantUsersForOrder(Order $order): Collection
    {
        return $this->merchantUsersForTenant($order->tenant_id);
    }

    public function merchantUsersForTenant(?int $tenantId): Collection
    {
        if ($tenantId !== null) {
            return User::query()
                ->whereHas('tenantMembers', function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)
                        ->where('status', 'active');
                })
                ->get();
        }

        return User::role([Role::ADMIN, Role::MANAGER])->get();
    }

    public function platformOwnerUsers(): Collection
    {
        return User::role(Role::ADMIN)->get();
    }

    private function push(UserNotification $notification, User $user, string $topic): void
    {
        $tokens = $this->deviceTokens->tokensFor($user)->all();

        if (empty($tokens)) {
            return;
        }

        try {
            $sent = $this->firebaseService->sendNotification((object) [
                'title' => $notification->title,
                'description' => $notification->body,
                'target_type' => $notification->target_type,
                'target_value' => $notification->target_value,
                'route_url' => $notification->route_url,
                'notification_id' => $notification->id,
                'event_key' => $notification->event_key,
                'audience' => $notification->audience,
                'tenant_id' => $notification->tenant_id,
                'data' => $notification->data ?: [],
                'order_id' => ($notification->target_type === 'order' && filled($notification->target_value))
                    ? $notification->target_value
                    : null,
            ], $tokens, $topic);

            if ($sent === true) {
                $notification->forceFill(['pushed_at' => now()])->save();
            }
        } catch (Throwable $throwable) {
            Log::info($throwable->getMessage());
        }
    }

    private function alert(string $eventKey): ?NotificationAlert
    {
        return NotificationAlert::query()
            ->where('language', $eventKey)
            ->first();
    }

    private function message(?NotificationAlert $alert, string $fallback, array $context): string
    {
        $message = $alert?->push_notification_message ?: $fallback;

        foreach ($context as $key => $value) {
            $message = str_replace(['{'.$key.'}', ':'.$key], (string) $value, $message);
        }

        return $message;
    }

    private function pushEnabled(?NotificationAlert $alert): bool
    {
        return $alert === null || (int) $alert->push_notification === SwitchBox::ON;
    }

    private function orderStatusConfig(int $status): ?array
    {
        return match ($status) {
            OrderStatus::PENDING => [
                'event_key' => 'order_pending_message',
                'title' => 'Order placed',
                'body' => 'Your order #{order_serial_no} has been placed.',
            ],
            OrderStatus::CONFIRMED => [
                'event_key' => 'order_confirmation_message',
                'title' => 'Order confirmed',
                'body' => 'Your order #{order_serial_no} has been confirmed.',
            ],
            OrderStatus::ON_THE_WAY => [
                'event_key' => 'order_on_the_way_message',
                'title' => 'Order out for delivery',
                'body' => 'Your order #{order_serial_no} is on the way.',
            ],
            OrderStatus::DELIVERED => [
                'event_key' => 'order_delivered_message',
                'title' => 'Order delivered',
                'body' => 'Your order #{order_serial_no} has been delivered.',
            ],
            OrderStatus::CANCELED => [
                'event_key' => 'order_canceled_message',
                'title' => 'Order canceled',
                'body' => 'Your order #{order_serial_no} has been canceled.',
            ],
            OrderStatus::REJECTED => [
                'event_key' => 'order_rejected_message',
                'title' => 'Order rejected',
                'body' => 'Your order #{order_serial_no} was rejected.',
            ],
            default => null,
        };
    }

    private function orderContext(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_serial_no' => $order->order_serial_no ?: $order->id,
        ];
    }

    private function orderData(Order $order): array
    {
        return [
            'order_id' => (string) $order->id,
            'target_id' => (string) $order->id,
            'target_type' => 'order',
            'target_value' => (string) $order->id,
            'tenant_id' => (string) ($order->tenant_id ?? ''),
        ];
    }

    private function returnData(ReturnAndRefund $returnAndRefund, Order $order): array
    {
        return array_merge($this->orderData($order), [
            'return_id' => (string) $returnAndRefund->id,
            'return_status' => (string) $returnAndRefund->status,
            'target_id' => (string) $returnAndRefund->id,
            'target_type' => 'return_order',
            'target_value' => (string) $returnAndRefund->id,
        ]);
    }
}
