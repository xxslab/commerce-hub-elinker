<?php

namespace App\Services\Orders;

class OrderStatusMapper
{
    public const STATUSES = [
        'NEW', 'PAID', 'PROCESSING', 'READY_TO_SHIP', 'SHIPPED',
        'COMPLETED', 'CANCELLED', 'REFUNDED', 'ON_HOLD', 'ERROR',
    ];

    public static function mapWoo(string $status): string
    {
        return match ($status) {
            'pending' => 'NEW',
            'processing' => 'PROCESSING',
            'on-hold' => 'ON_HOLD',
            'completed' => 'COMPLETED',
            'cancelled', 'failed' => 'CANCELLED',
            'refunded' => 'REFUNDED',
            default => 'ERROR',
        };
    }

    public static function normalize(string $source, ?string $fulfillment, ?string $payment = null, ?string $shipping = null): string
    {
        $value = strtolower((string) ($fulfillment ?: $shipping ?: ''));

        if ($value === '' && in_array(strtolower((string) $payment), ['paid', 'confirmed'], true)) return 'PAID';

        return match ($source) {
            'allegro' => match ($value) {
                'new' => 'NEW',
                'ready_for_processing' => in_array(strtolower((string) $payment), ['paid', 'confirmed'], true) ? 'PAID' : 'PROCESSING',
                'processing', 'ready_for_shipment' => 'PROCESSING',
                'sent' => 'SHIPPED',
                'cancelled' => 'CANCELLED',
                'completed' => 'COMPLETED',
                default => 'ERROR',
            },
            'ebay' => match ($value) {
                'in_progress', 'pending' => 'PROCESSING',
                'fulfilled', 'shipped' => 'SHIPPED',
                'completed' => 'COMPLETED',
                'cancelled' => 'CANCELLED',
                default => 'NEW',
            },
            default => 'ERROR',
        };
    }
}
