<?php

namespace Tests\Unit;

use App\Services\Orders\OrderStatusMapper;
use PHPUnit\Framework\TestCase;

class OrderStatusMapperTest extends TestCase
{
    public function test_woocommerce_statuses_are_normalized(): void
    {
        self::assertSame('NEW', OrderStatusMapper::mapWoo('pending'));
        self::assertSame('PROCESSING', OrderStatusMapper::mapWoo('processing'));
        self::assertSame('COMPLETED', OrderStatusMapper::mapWoo('completed'));
        self::assertSame('REFUNDED', OrderStatusMapper::mapWoo('refunded'));
    }

    public function test_payment_status_does_not_become_fulfilment_status_without_mapping(): void
    {
        self::assertSame('PAID', OrderStatusMapper::normalize('allegro', 'ready_for_processing', 'paid'));
        self::assertSame('SHIPPED', OrderStatusMapper::normalize('ebay', 'FULFILLED', 'PAID'));
    }
}
