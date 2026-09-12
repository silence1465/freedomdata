<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SmsDevice extends Model
{
    protected $table = 'sms_devices';
    protected $fillable = ['device_name', 'device_identifier', 'api_token_hash', 'is_active', 'last_seen_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function incomingSms() { return $this->hasMany(IncomingSms::class, 'device_id'); }

    /** Generate a new token and return the plain text version (only shown once) */
    public static function generateToken(): array
    {
        $plain = 'sms_' . Str::random(40);
        return ['plain' => $plain, 'hash' => hash('sha256', $plain)];
    }

    /** Verify a plain token against this device */
    public function verifyToken(string $plain): bool
    {
        return hash('sha256', $plain) === $this->api_token_hash;
    }
}
