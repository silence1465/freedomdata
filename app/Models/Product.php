<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasUuids;

    protected $fillable = ['data_sika_id','network','bundle_gb','cost_price','sell_price','agent_price','service_type','is_available'];

    protected function casts(): array
    {
        return ['bundle_gb'=>'decimal:2','cost_price'=>'decimal:2','sell_price'=>'decimal:2','agent_price'=>'decimal:2','is_available'=>'boolean'];
    }

    public function orders() { return $this->hasMany(Order::class); }
}
