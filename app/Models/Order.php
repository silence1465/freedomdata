<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasUuids;

    public const TERMINAL = ['DELIVERED','FAILED','REFUNDED'];

    protected $fillable = ['idempotency_key','data_sika_order_id','user_id','product_id','recipient','amount_charged','cost_amount','status','failure_reason','poll_attempts'];

    protected function casts(): array
    {
        return ['amount_charged'=>'decimal:2','cost_amount'=>'decimal:2','poll_attempts'=>'integer'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function isTerminal(): bool { return in_array($this->status, self::TERMINAL, true); }
}
