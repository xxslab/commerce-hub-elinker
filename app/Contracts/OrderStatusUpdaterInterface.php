<?php
namespace App\Contracts;

interface OrderStatusUpdaterInterface
{
    public function update(string $externalOrderId, string $status): array;
}
