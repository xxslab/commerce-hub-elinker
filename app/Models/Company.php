<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = ['name', 'tax_id', 'email'];

    protected $casts = [
        'entitlement_features' => 'array',
        'entitlement_checked_at' => 'datetime',
    ];

    public function salesChannels(): HasMany
    {
        return $this->hasMany(SalesChannel::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CommerceOrder::class);
    }
}
