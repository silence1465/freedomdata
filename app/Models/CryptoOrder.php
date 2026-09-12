<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CryptoOrder extends Model
{
    use HasUuids;

    public const TERMINAL = ['COMPLETED','CANCELLED'];

    protected $fillable = ['user_id','type','ghs_amount','usdt_amount','rate_used','network','customer_wallet_address','tx_hash','status','admin_notes'];

    protected function casts(): array
    {
        return ['ghs_amount'=>'decimal:2','usdt_amount'=>'decimal:4','rate_used'=>'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function isTerminal(): bool { return in_array($this->status, self::TERMINAL, true); }
}
