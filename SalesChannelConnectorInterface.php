<?php
namespace App\Contracts;

interface SalesChannelConnectorInterface extends ConnectionTesterInterface, OrderImporterInterface, OrderStatusUpdaterInterface
{
}
