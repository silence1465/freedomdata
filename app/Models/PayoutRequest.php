<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'method', 'account_name', 'account_number', 'provider', 'status', 'admin_notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function isPending(): bool { return $this->status === 'PENDING'; }
}
