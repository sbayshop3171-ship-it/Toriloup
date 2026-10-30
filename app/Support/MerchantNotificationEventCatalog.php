<?php

namespace App\Support;

class MerchantNotificationEventCatalog
{
    /**
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function events(): array
    {
        return [
            ['key' => 'admin_and_manager_new_order_message', 'label' => 'New order', 'description' => 'A customer places a new order.'],
            ['key' => 'merchant_payment_received_message', 'label' => 'Payment received', 'description' => 'A customer payment succeeds.'],
            ['key' => 'merchant_payment_failed_message', 'label' => 'Payment failed', 'description' => 'A customer payment fails.'],
            ['key' => 'merchant_order_canceled_message', 'label' => 'Order canceled', 'description' => 'A customer cancels an order.'],
            ['key' => 'return_requested_message', 'label' => 'Return requested', 'description' => 'A customer requests a return or refund.'],
            ['key' => 'merchant_return_approved_message', 'label' => 'Return approved', 'description' => 'A return or refund request is approved.'],
            ['key' => 'merchant_return_rejected_message', 'label' => 'Return rejected', 'description' => 'A return or refund request is rejected.'],
            ['key' => 'merchant_new_review_message', 'label' => 'New product review', 'description' => 'A customer leaves a product review.'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::events(), 'key');
    }

    /**
     * @return array{key: string, label: string, description: string}|null
     */
    public static function find(string $key): ?array
    {
        foreach (self::events() as $event) {
            if ($event['key'] === $key) {
                return $event;
            }
        }

        return null;
    }
}
