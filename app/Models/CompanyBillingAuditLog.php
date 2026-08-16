<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBillingAuditLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['company_id', 'event', 'workspace_id', 'ip_address', 'context'];

    protected $casts = [
        'context' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
