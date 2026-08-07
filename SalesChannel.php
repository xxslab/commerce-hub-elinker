<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_orders_sync_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];
}
