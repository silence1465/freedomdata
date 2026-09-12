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
        $orders = Order::whereIn('status', ['PENDING', 'PROCESSING', 'ON_HOLD'])
            ->whereNotNull('data_sika_order_id')
            ->where('data_sika_order_id', 'not like', 'MOCK-%')
            ->where('created_at', '>=', now()->subDays(14))
            ->get();

        if ($orders->isEmpty()) return;

        foreach ($orders as $order) {
            try {
                $result = $ds->getOrderStatus($order->data_sika_order_id);

                $rawStatus = strtolower($result['status'] ?? '');
                $isOnHold = $result['held_for_review'] ?? false;
                $rawStatusLabel = $result['raw_status'] ?? null;
                $message = $result['failure_reason'] ?? null;

                $newStatus = match(true) {
                    $isOnHold => 'ON_HOLD',
                    in_array($rawStatus, ['delivered', 'completed', 'success', 'successful']) => 'DELIVERED',
                    in_array($rawStatus, ['processing', 'in_progress', 'sending']) => 'PROCESSING',
                    in_array($rawStatus, ['pending']) => 'PENDING',
                    in_array($rawStatus, ['failed', 'cancelled', 'error', 'rejected']) => 'FAILED',
                    default => null,
                };

                // Always save the latest message from DataSika
                $updateData = [
                    'datasika_raw_status' => $rawStatusLabel,
                    'datasika_message' => $message,
                ];

                if ($newStatus && $newStatus !== $order->status) {
                    $updateData['status'] = $newStatus;

                    if ($newStatus === 'DELIVERED') {
                        \App\Models\Notification::send(
                            $order->user_id,
                            'Bundle delivered ✓',
                            $order->product->network . ' ' . intval($order->product->bundle_gb) . 'GB sent to ' . $order->recipient . '.',
                            'success'
                        );
                    }

                    if ($newStatus === 'FAILED') {
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

                $order->update($updateData);

            } catch (\Exception $e) {
                Log::error('SyncOrderStatuses failed for order ' . $order->id . ': ' . $e->getMessage());
            }
        }
    }
}
