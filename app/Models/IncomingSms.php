<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingSms extends Model
{
    protected $table = 'incoming_sms';
    protected $fillable = ['device_id', 'sender', 'message', 'message_hash', 'received_at', 'processed', 'processing_status'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime', 'processed' => 'boolean'];
    }

    public function device() { return $this->belongsTo(SmsDevice::class, 'device_id'); }
    public function transaction() { return $this->hasOne(SmsPaymentTransaction::class, 'incoming_sms_id'); }
}
