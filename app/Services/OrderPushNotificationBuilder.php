<?php

namespace App\Services;

use App\Models\Order;

class OrderPushNotificationBuilder
{
    public int $orderId;
    public mixed $status;
    public ?Order $order;

    public function __construct($orderId, $status)
    {
        $this->orderId = $orderId;
        $this->status = $status;
        $this->order = Order::withoutGlobalScopes()->find($orderId);
    }

    public function send(): void
    {
        if ($this->order instanceof Order) {
            app(TransactionalNotificationService::class)->sendOrderStatusToCustomer($this->order, (int) $this->status);
        }
    }
}
