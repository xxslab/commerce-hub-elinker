<?php
namespace App\Contracts;

interface ConnectionTesterInterface
{
    public function testConnection(): array;
}
