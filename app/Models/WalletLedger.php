<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLedger extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id','order_id','type','amount','balance_after','reference','created_at'];

    protected function casts(): array
    {
        return ['amount'=>'decimal:2','balance_after'=>'decimal:2','created_at'=>'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
}
