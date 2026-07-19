<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PaymentGatewayCredentials
{
    public static function stripePublishableKey(array|Collection $options): string
    {
        $stripeKey = self::option($options, 'stripe_key');
        $stripeSecret = self::option($options, 'stripe_secret');

        if (self::isStripePublishableKey($stripeKey)) {
            return $stripeKey;
        }

        if (self::isStripePublishableKey($stripeSecret)) {
            return $stripeSecret;
        }

        return $stripeKey;
    }

    public static function stripeSecretKey(array|Collection $options): string
    {
        $stripeKey = self::option($options, 'stripe_key');
        $stripeSecret = self::option($options, 'stripe_secret');

        if (self::isStripeSecretKey($stripeSecret)) {
            return $stripeSecret;
        }

        if (self::isStripeSecretKey($stripeKey)) {
            return $stripeKey;
        }

        return $stripeSecret;
    }

    public static function normalizeStripeOptions(array $options): array
    {
        $stripeKey = trim((string) ($options['stripe_key'] ?? ''));
        $stripeSecret = trim((string) ($options['stripe_secret'] ?? ''));

        if (self::isStripeSecretKey($stripeKey) && self::isStripePublishableKey($stripeSecret)) {
            $options['stripe_key'] = $stripeSecret;
            $options['stripe_secret'] = $stripeKey;
        }

        return $options;
    }

    private static function option(array|Collection $options, string $key): string
    {
        $value = $options instanceof Collection ? $options->get($key) : ($options[$key] ?? '');

        return trim((string) $value);
    }

    private static function isStripePublishableKey(string $value): bool
    {
        return str_starts_with($value, 'pk_');
    }

    private static function isStripeSecretKey(string $value): bool
    {
        return str_starts_with($value, 'sk_') || str_starts_with($value, 'rk_');
    }
}
