<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ordered_at' => 'datetime',
    ];

    public function channel()
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
