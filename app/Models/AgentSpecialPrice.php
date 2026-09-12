<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentSpecialPrice extends Model
{
    protected $fillable = ['user_id', 'product_id', 'special_price'];

    protected function casts(): array
    {
        return ['special_price' => 'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
