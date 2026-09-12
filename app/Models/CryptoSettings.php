<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoSettings extends Model
{
    protected $fillable = ['buy_rate','sell_rate','network','wallet_address','min_ghs','max_ghs','is_enabled','buy_enabled','sell_enabled','bundle_enabled','paystack_enabled'];

    protected function casts(): array
    {
        return ['buy_rate'=>'decimal:2','sell_rate'=>'decimal:2','min_ghs'=>'decimal:2','max_ghs'=>'decimal:2','is_enabled'=>'boolean','buy_enabled'=>'boolean','sell_enabled'=>'boolean','bundle_enabled'=>'boolean','paystack_enabled'=>'boolean'];
    }

    public static function current(): self { return self::firstOrCreate(['id' => 1]); }
}
