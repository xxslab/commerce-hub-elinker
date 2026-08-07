<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CarrierAccount extends Model
{
    protected $guarded = [];
    protected $casts = ['is_enabled' => 'boolean'];
    public function setCredentials(array $value): void { $this->credentials_encrypted = encrypt(json_encode($value)); }
    public function credentials(): array { try { return $this->credentials_encrypted ? (json_decode(decrypt($this->credentials_encrypted), true) ?: []) : []; } catch (\Throwable) { return []; } }
}
