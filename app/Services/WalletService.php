<?php
namespace App\Services;

use App\Models\User;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function credit(int $userId, float $amount, string $type, ?string $orderId = null, ?string $ref = null): array
    {
        return $this->mutate($userId, $amount, $type, $orderId, $ref);
    }

    public function debit(int $userId, float $amount, string $type, ?string $orderId = null, ?string $ref = null): array
    {
        return $this->mutate($userId, -$amount, $type, $orderId, $ref);
    }

    private function mutate(int $userId, float $signed, string $type, ?string $orderId, ?string $ref): array
    {
        return DB::transaction(function () use ($userId, $signed, $type, $orderId, $ref) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
            $new = round((float) $user->wallet_balance + $signed, 2);

            if ($new < 0) {
                throw new \Exception('insufficient_balance');
            }

            $user->wallet_balance = $new;
            $user->save();

            $entry = WalletLedger::create([
                'user_id' => $userId, 'order_id' => $orderId, 'type' => $type,
                'amount' => $signed, 'balance_after' => $new, 'reference' => $ref,
                'created_at' => now(),
            ]);

            return ['user' => $user, 'entry' => $entry];
        });
    }
}
