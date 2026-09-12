<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SmsDeposit extends Model
{
    protected $table = 'sms_deposits';
    protected $fillable = ['user_id', 'payment_reference', 'expected_amount', 'received_amount', 'status', 'payer_phone', 'transaction_reference', 'purpose', 'purpose_meta', 'expires_at', 'completed_at'];

    protected function casts(): array
    {
        return ['expected_amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'expires_at' => 'datetime', 'completed_at' => 'datetime', 'purpose_meta' => 'array'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(SmsPaymentTransaction::class, 'deposit_id'); }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isExpired(): bool { return $this->expires_at->isPast() && $this->status === 'pending'; }

    /** Generate a unique reference with FD- prefix */
    public static function generateReference(): string
    {
        do {
            $ref = 'FD-' . strtoupper(Str::random(6));
        } while (self::where('payment_reference', $ref)->exists());
        return $ref;
    }
}