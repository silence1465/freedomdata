<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name','phone','email','password','wallet_balance','agent_expires_at','agent_code'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at'=>'datetime','password'=>'hashed','wallet_balance'=>'decimal:2','agent_expires_at'=>'datetime'];
    }

    public function orders() { return $this->hasMany(Order::class); }
    public function cryptoOrders() { return $this->hasMany(CryptoOrder::class); }
    public function walletLedgers() { return $this->hasMany(WalletLedger::class); }
    public function agentResellPrices() { return $this->hasMany(AgentResellPrice::class); }
    public function agentSpecialPrices() { return $this->hasMany(AgentSpecialPrice::class); }
    public function exclusiveProducts() { return $this->belongsToMany(Product::class, 'agent_exclusive_products'); }
    public function notifications() { return $this->hasMany(Notification::class); }
    public function payoutRequests() { return $this->hasMany(PayoutRequest::class); }
    public function isAdmin(): bool { return $this->role === 'ADMIN'; }

    public function unreadNotificationCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }

    public function isActiveAgent(): bool
    {
        return $this->role === 'AGENT' && $this->agent_expires_at && $this->agent_expires_at->isFuture();
    }
}
