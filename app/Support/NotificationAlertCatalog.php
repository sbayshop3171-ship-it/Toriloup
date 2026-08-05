<?php

namespace App\Support;

use App\Enums\SwitchBox;

class NotificationAlertCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return [
            self::row('Order Pending Message', 'order_pending_message', 'Your order has been placed.'),
            self::row('Order Confirmation Message', 'order_confirmation_message', 'Your order has been confirmed.'),
            self::row('Order On The Way Message', 'order_on_the_way_message', 'Your order is on the way.'),
            self::row('Order Delivered Message', 'order_delivered_message', 'Your order has been delivered.'),
            self::row('Order Canceled Message', 'order_canceled_message', 'Your order has been canceled.'),
            self::row('Order Rejected Message', 'order_rejected_message', 'Your order was rejected.'),
            self::row('Customer Payment Successful Message', 'payment_successful_message', 'Your payment was successful.'),
            self::row('Customer Payment Failed Message', 'payment_failed_message', 'Your payment could not be completed.'),
            self::row('Return Request Approved Message', 'return_approved_message', 'Your return/refund request was approved.'),
            self::row('Return Request Rejected Message', 'return_rejected_message', 'Your return/refund request was rejected.'),
            self::row('Refund Processed Message', 'refund_processed_message', 'Your refund has been processed.'),
            self::row('Admin And Manager New Order Message', 'admin_and_manager_new_order_message', 'You have a new order.'),
            self::row('Merchant Payment Received Message', 'merchant_payment_received_message', 'Payment received for an order.'),
            self::row('Merchant Payment Failed Message', 'merchant_payment_failed_message', 'Payment failed for an order.'),
            self::row('Merchant Order Canceled Message', 'merchant_order_canceled_message', 'A customer canceled an order.'),
            self::row('Merchant Return Request Message', 'return_requested_message', 'A customer requested a return/refund.'),
            self::row('Merchant Return Approved Message', 'merchant_return_approved_message', 'A return/refund request was approved.'),
            self::row('Merchant Return Rejected Message', 'merchant_return_rejected_message', 'A return/refund request was rejected.'),
            self::row('Merchant New Review Message', 'merchant_new_review_message', 'A customer left a new review.'),
            self::row('Merchant Low Stock Message', 'merchant_low_stock_message', 'A product is running low on stock.'),
            self::row('Merchant Payout Update Message', 'merchant_payout_update_message', 'Your payout or settlement status changed.'),
            self::row('Merchant Daily Summary Message', 'merchant_daily_summary_message', 'Your store summary is ready.'),
            self::row('Customer Support Reply Message', 'customer_support_reply_message', 'You have a new support reply.'),
            self::row('Merchant Customer Message Message', 'merchant_customer_message_message', 'You have a new customer message.'),
            self::row('Platform Merchant Registered Message', 'platform_merchant_registered_message', 'A new merchant registered a store.'),
            self::row('Platform Merchant Payment Message', 'platform_merchant_payment_message', 'A merchant billing payment status changed.'),
            self::row('Platform Domain Issue Message', 'platform_domain_issue_message', 'A merchant domain needs attention.'),
            self::row('Platform Payment Gateway Error Message', 'platform_payment_gateway_error_message', 'A payment gateway error was detected.'),
            self::row('Platform Push Delivery Failed Message', 'platform_push_delivery_failed_message', 'A push notification delivery failed.'),
            self::row('Platform Security Alert Message', 'platform_security_alert_message', 'A suspicious login or security event was detected.'),
            self::row('Platform Support Escalation Message', 'platform_support_escalation_message', 'A support item was escalated.'),
            self::row('Platform Daily Summary Message', 'platform_daily_summary_message', 'Your platform summary is ready.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(string $name, string $language, string $message): array
    {
        return [
            'name' => $name,
            'language' => $language,
            'mail_message' => $message,
            'sms_message' => $message,
            'push_notification_message' => $message,
            'mail' => SwitchBox::OFF,
            'sms' => SwitchBox::OFF,
            'push_notification' => SwitchBox::ON,
        ];
    }
}
