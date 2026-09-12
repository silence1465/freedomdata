<?php
namespace App\Console\Commands;

use App\Models\Order;
use App\Services\DataSikaClient;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync-statuses';
    protected $description = 'Poll DataSika for pending/processing order status updates';

    public function handle(DataSikaClient $ds, WalletService $wallet): void
    {
        $orders = Order::whereIn('status', ['PENDING', 'PROCESSING'])
            ->whereNotNull('data_sika_order_id')
            ->where('data_sika_order_id', 'not like', 'MOCK-%')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($orders as $order) {
            try {
                $result = $ds->getOrderStatus($order->data_sika_order_id);

                Log::info('DataSika status check', [
                    'order_id' => $order->id,
                    'datasika_response' => $result,
                ]);

                // Handle whatever status field DataSika returns
                $rawStatus = strtolower(
                    $result['status'] ?? $result['order_status'] ?? $result['data']['status'] ?? ''
                );

                $newStatus = match(true) {
                    in_array($rawStatus, ['delivered', 'completed', 'success', 'successful']) => 'DELIVERED',
                    in_array($rawStatus, ['processing', 'in_progress', 'sending']) => 'PROCESSING',
                    in_array($rawStatus, ['pending']) => 'PENDING',
                    in_array($rawStatus, ['failed', 'cancelled', 'error', 'rejected']) => 'FAILED',
                    default => null,
                };

                if ($newStatus && $newStatus !== $order->status) {
                    $order->update(['status' => $newStatus]);

                    if ($newStatus === 'DELIVERED') {
                        \App\Models\Notification::send(
                            $order->user_id,
                            'Bundle delivered ✓',
                            $order->product->network . ' ' . intval($order->product->bundle_gb) . 'GB sent to ' . $order->recipient . '.',
                            'success'
                        );
                    }

                    if ($newStatus === 'FAILED') {
                        // Refund wallet if they paid with wallet
                        $ledger = \App\Models\WalletLedger::where('order_id', $order->id)->where('amount', '<', 0)->first();
                        if ($ledger) {
                            $wallet->credit($order->user_id, abs($ledger->amount), 'REFUND', null, 'failed_' . $order->id);
                        }
                        \App\Models\Notification::send(
                            $order->user_id,
                            'Order failed',
                            'Your bundle order for ' . $order->recipient . ' failed. Contact support.',
                            'alert'
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error('SyncOrderStatuses failed for order ' . $order->id . ': ' . $e->getMessage());
            }
        }
    }
}
