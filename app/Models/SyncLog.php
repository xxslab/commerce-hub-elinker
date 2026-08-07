<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SalesChannel;

class SyncLog extends Model
{
    protected $fillable = [
        'sales_channel_id', 'shipment_id', 'type', 'status', 'message',
        'records_count', 'context', 'started_at', 'finished_at'
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function salesChannel()
    {
        return $this->belongsTo(SalesChannel::class);
    }
}
