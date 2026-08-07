<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'ordered_at' => 'datetime',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'raw_payload' => 'array',
    ];

    public function channel()
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function salesChannel()
    {
        return $this->channel();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function maskedEmail(): ?string
    {
        if (! $this->customer_email || ! str_contains($this->customer_email, '@')) return $this->customer_email;
        [$name, $domain] = explode('@', $this->customer_email, 2);
        return mb_substr($name, 0, 1) . '***@' . $domain;
    }

    public function maskedPhone(): ?string
    {
        if (! $this->customer_phone) return null;
        return str_repeat('*', max(0, mb_strlen($this->customer_phone) - 3)) . mb_substr($this->customer_phone, -3);
    }

    public function safeIntegrationPayload(): array
    {
        $payload = is_array($this->raw_payload) ? $this->raw_payload : [];
        foreach (['billing', 'shipping', 'customer_email', 'email', 'phone', 'customer_phone', 'password', 'consumer_key', 'consumer_secret', 'access_token', 'refresh_token'] as $key) unset($payload[$key]);
        return $payload;
    }
}
