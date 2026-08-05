<?php

namespace App\Services;

use App\Models\Order;

class OrderGotPushNotificationBuilder
{
    public int $orderId;
    public ?Order $order;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
        $this->order = Order::withoutGlobalScopes()->find($orderId);
    }

    public function send(): void
    {
        if ($this->order instanceof Order) {
            app(TransactionalNotificationService::class)->sendNewOrderToMerchant($this->order);
        }
    }
}
