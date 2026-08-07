<?php
namespace App\Contracts;

interface OrderImporterInterface
{
    public function import(): array;
}
