<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    protected $fillable = [
        'company_id', 'sales_channel_id', 'operation', 'status', 'started_at',
        'finished_at', 'fetched_count', 'created_count', 'updated_count',
        'skipped_count', 'error_count', 'error_code', 'correlation_id', 'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function salesChannel() { return $this->belongsTo(SalesChannel::class); }
}
