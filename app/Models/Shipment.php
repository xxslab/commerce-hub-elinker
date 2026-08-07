<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $fillable = [
        'company_id', 'commerce_order_id', 'carrier', 'external_shipment_id',
        'tracking_number', 'status', 'label_format', 'label_path',
        'last_tracking_sync_at', 'request_payload', 'raw_payload'
    ];

    protected $casts = [
        'last_tracking_sync_at' => 'datetime',
        'request_payload' => 'array',
        'raw_payload' => 'array',
    ];

    public function events() { return $this->hasMany(ShipmentEvent::class); }
    public function labels() { return $this->hasMany(ShippingLabel::class); }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CommerceOrder::class, 'commerce_order_id');
    }
}
