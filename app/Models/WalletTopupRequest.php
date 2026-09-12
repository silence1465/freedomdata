<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTopupRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'transaction_id', 'mtn_reference', 'sender_name', 'status', 'admin_notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function isPending(): bool { return $this->status === 'PENDING'; }
}
