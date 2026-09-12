<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsPaymentTransaction extends Model
{
    protected $table = 'sms_payment_transactions';
    protected $fillable = ['deposit_id', 'incoming_sms_id', 'transaction_id', 'payer_phone', 'amount', 'raw_message', 'match_method', 'match_confidence', 'approved_by', 'status'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'match_confidence' => 'decimal:2'];
    }

    public function deposit() { return $this->belongsTo(SmsDeposit::class, 'deposit_id'); }
    public function sms() { return $this->belongsTo(IncomingSms::class, 'incoming_sms_id'); }
}
