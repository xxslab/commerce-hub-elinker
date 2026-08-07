<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesChannel extends Model
{
    public const TYPE_WOOCOMMERCE = 'woocommerce';
    public const TYPE_ALLEGRO = 'allegro';
    public const TYPE_EBAY = 'ebay';

    protected $guarded = [];

    protected $casts = [
        'last_orders_sync_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'last_sync_started_at' => 'datetime',
        'last_sync_finished_at' => 'datetime',
        'last_successful_sync_at' => 'datetime',
        'is_enabled' => 'boolean',
        'settings' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function orders()
    {
        return $this->hasMany(CommerceOrder::class, 'sales_channel_id');
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = encrypt(json_encode($credentials));
    }

    public function getCredentials(): array
    {
        if (!$this->credentials_encrypted) {
            return [];
        }

        try {
            $decoded = json_decode(decrypt($this->credentials_encrypted), true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
