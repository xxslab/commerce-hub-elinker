<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class MarketplaceAppCredential extends Model
{
    public const MARKETPLACE_ALLEGRO = 'allegro';
    public const MARKETPLACE_EBAY = 'ebay';

    protected $fillable = [
        'company_id',
        'marketplace',
        'environment',
        'name',
        'client_id',
        'client_secret_encrypted',
        'redirect_uri',
        'scopes',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setClientSecret(?string $secret): void
    {
        $this->client_secret_encrypted = $secret ? Crypt::encryptString($secret) : null;
    }

    public function getClientSecret(): ?string
    {
        return $this->client_secret_encrypted ? Crypt::decryptString($this->client_secret_encrypted) : null;
    }

    public function scopeForCompanyMarketplace($query, int $companyId, string $marketplace)
    {
        return $query->where('company_id', $companyId)
            ->where('marketplace', $marketplace)
            ->where('is_active', true)
            ->latest('id');
    }
}
