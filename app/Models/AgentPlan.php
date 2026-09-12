<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPlan extends Model
{
    protected $fillable = ['monthly_fee', 'yearly_fee', 'is_enabled'];

    protected function casts(): array
    {
        return ['monthly_fee' => 'decimal:2', 'yearly_fee' => 'decimal:2', 'is_enabled' => 'boolean'];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }
}
