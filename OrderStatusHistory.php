<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'company_id', 'commerce_order_id', 'user_id', 'from_status', 'to_status',
        'source', 'sync_status', 'error_message',
    ];

    public function order() { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
