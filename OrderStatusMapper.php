<?php

namespace App\Services\Orders;

class OrderStatusMapper
{
    public static function mapWoo(string $status): string
    {
        return match ($status) {
            'pending' => 'NEW',
            'processing', 'on-hold' => 'PAID',
            'completed' => 'COMPLETED',
            'cancelled', 'failed' => 'CANCELLED',
            'refunded' => 'REFUNDED',
            default => 'PROCESSING',
        };
    }
}
