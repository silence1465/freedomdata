<?php
namespace App\Console\Commands;

use App\Models\SmsDeposit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireDeposits extends Command
{
    protected $signature = 'deposits:expire';
    protected $description = 'Mark expired unpaid deposits and clean up old ones';

    public function handle(): void
    {
        // Mark pending deposits past their expiry time as expired
        $expired = SmsDeposit::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        if ($expired > 0) {
            Log::info("ExpireDeposits: marked {$expired} deposits as expired.");
        }

        // Delete completed/expired/failed deposits older than 30 days
        $deleted = SmsDeposit::whereIn('status', ['expired', 'failed'])
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        if ($deleted > 0) {
            Log::info("ExpireDeposits: deleted {$deleted} old deposits.");
        }
    }
}
