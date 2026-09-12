<?php
namespace App\Services;

use App\Models\IncomingSms;
use App\Models\SmsDeposit;
use App\Models\SmsPaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentMatcher
{
    /** Amount tolerance: 2% difference allowed (covers rounding) */
    private float $amountTolerance = 0.02;

    /**
     * Try to match parsed SMS to a pending deposit.
     * Returns the match result.
     */
    public function match(IncomingSms $sms, array $parsed): array
    {
        if (! $parsed['is_payment_sms']) {
            return ['matched' => false, 'reason' => 'not_payment_sms'];
        }

        if (! $parsed['amount']) {
            return ['matched' => false, 'reason' => 'no_amount_parsed'];
        }

        // SECURITY CHECK 1: SMS must come from a real MTN sender ID
        // Valid senders: "Mobile Money", "MTN", "MoMo", "1414"
        // Invalid: regular phone numbers like 0241234567
        $parser = app(SmsParser::class);
        if (! $parser->isMoMoSender($sms->sender)) {
            $sms->update(['processing_status' => 'rejected']);
            Log::warning('SmsPayment: SECURITY — SMS not from a known MoMo sender.', [
                'sender' => $sms->sender,
            ]);
            return ['matched' => false, 'reason' => 'unknown_sender'];
        }

        // SECURITY CHECK 2: Must have our FD- reference code to auto-approve
        // Without our reference, goes to manual review only
        if (! $parsed['payment_reference'] || strpos($parsed['payment_reference'], 'FD-') !== 0) {
            $sms->update(['processing_status' => 'manual_review']);
            Log::info('SmsPayment: no FD- reference, flagging for manual review', [
                'amount' => $parsed['amount'],
                'sender' => $sms->sender,
            ]);
            return ['matched' => false, 'reason' => 'no_freedom_reference', 'manual_review' => true];
        }

        // SECURITY CHECK 3: Check for duplicate transaction ID
        if ($parsed['transaction_id']) {
            $duplicate = SmsPaymentTransaction::where('transaction_id', $parsed['transaction_id'])->exists();
            if ($duplicate) {
                $sms->update(['processing_status' => 'rejected']);
                Log::warning('SmsPayment: SECURITY — duplicate transaction ID', [
                    'transaction_id' => $parsed['transaction_id'],
                ]);
                return ['matched' => false, 'reason' => 'duplicate_transaction_id'];
            }
        }

        // Level 1: Exact reference + exact amount match → AUTO APPROVE
        $deposit = SmsDeposit::where('payment_reference', $parsed['payment_reference'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $deposit) {
            $sms->update(['processing_status' => 'manual_review']);
            return ['matched' => false, 'reason' => 'reference_not_found', 'manual_review' => true];
        }

        // Already paid — second payment to same reference
        if ($deposit->status !== 'pending') {
            $sms->update(['processing_status' => 'manual_review']);
            Log::warning('SmsPayment: SECURITY — payment received for already-' . $deposit->status . ' deposit', [
                'reference' => $parsed['payment_reference'],
                'amount'    => $parsed['amount'],
            ]);
            $admins = \App\Models\User::where('role', 'ADMIN')->get();
            foreach ($admins as $admin) {
                \App\Models\Notification::send(
                    $admin->id,
                    'Duplicate payment received',
                    '₵' . number_format($parsed['amount'], 2) . " received for ref {$parsed['payment_reference']} which is already {$deposit->status}. Manual refund may be needed.",
                    'alert'
                );
            }
            return ['matched' => false, 'reason' => 'deposit_already_' . $deposit->status];
        }

        // SECURITY CHECK 4: Received must be >= expected (overpayment ok, underpayment not)
        if ((float) $parsed['amount'] < (float) $deposit->expected_amount) {
            Log::warning('SmsPayment: underpayment — amount too low', [
                'reference' => $parsed['payment_reference'],
                'expected'  => $deposit->expected_amount,
                'received'  => $parsed['amount'],
            ]);
            return $this->manualReview($sms, $deposit, $parsed, 'underpayment');
        }

        // SECURITY CHECK 5: Deposit must not be expired
        if ($deposit->isExpired()) {
            $sms->update(['processing_status' => 'rejected']);
            return ['matched' => false, 'reason' => 'deposit_expired'];
        }

        // All checks passed — auto approve
        return $this->completeMatch($sms, $deposit, $parsed, 'reference', $parsed['confidence']);
    }

    private function completeMatch(IncomingSms $sms, SmsDeposit $deposit, array $parsed, string $method, float $confidence): array
    {
        return DB::transaction(function () use ($sms, $deposit, $parsed, $method, $confidence) {
            $deposit = SmsDeposit::where('id', $deposit->id)->where('status', 'pending')->lockForUpdate()->first();
            if (! $deposit) {
                return ['matched' => false, 'reason' => 'deposit_no_longer_pending'];
            }

            if ($parsed['transaction_id']) {
                $exists = SmsPaymentTransaction::where('transaction_id', $parsed['transaction_id'])->exists();
                if ($exists) {
                    return ['matched' => false, 'reason' => 'duplicate_transaction_id'];
                }
            }

            $receivedAmount = (float) $parsed['amount'];
            $expectedAmount = (float) $deposit->expected_amount;
            $overpayment    = round($receivedAmount - $expectedAmount, 2);

            SmsPaymentTransaction::create([
                'deposit_id'       => $deposit->id,
                'incoming_sms_id'  => $sms->id,
                'transaction_id'   => $parsed['transaction_id'],
                'payer_phone'      => $parsed['sender_phone'],
                'amount'           => $receivedAmount,
                'raw_message'      => $sms->message,
                'match_method'     => $method,
                'match_confidence' => $confidence,
                'status'           => 'completed',
            ]);

            $deposit->update([
                'status'                => 'completed',
                'received_amount'       => $receivedAmount,
                'payer_phone'           => $parsed['sender_phone'],
                'transaction_reference' => $parsed['transaction_id'],
                'completed_at'          => now(),
            ]);

            $sms->update(['processed' => true, 'processing_status' => 'matched']);

            // Fulfill the deposit purpose (send bundle, top up wallet, etc.)
            $this->fulfillDeposit($deposit);

            // Handle overpayment
            if ($overpayment > 0) {
                $guestUserId = \App\Models\User::where('phone', '0000000000')->value('id');
                $isRealUser = $deposit->user_id && $deposit->user_id !== $guestUserId;

                if ($isRealUser) {
                    // Logged-in user — credit excess to their wallet
                    $wallet = app(WalletService::class);
                    $wallet->credit(
                        $deposit->user_id,
                        $overpayment,
                        'OVERPAYMENT_REFUND',
                        null,
                        'overpay_' . $deposit->payment_reference
                    );
                    \App\Models\Notification::send(
                        $deposit->user_id,
                        'Overpayment credited',
                        '₵' . number_format($overpayment, 2) . ' extra you sent has been added to your wallet.',
                        'success'
                    );
                } else {
                    // Guest — notify admin to refund manually
                    $admins = \App\Models\User::where('role', 'ADMIN')->get();
                    foreach ($admins as $admin) {
                        \App\Models\Notification::send(
                            $admin->id,
                            'Guest overpayment',
                            "Guest paid ₵" . number_format($receivedAmount, 2) . " but only ₵" . number_format($expectedAmount, 2) . " was expected (Ref: {$deposit->payment_reference}). Overpayment: ₵" . number_format($overpayment, 2) . ". Refund manually if needed.",
                            'warning'
                        );
                    }
                }

                Log::info('Overpayment handled', [
                    'deposit_id' => $deposit->id,
                    'expected'   => $expectedAmount,
                    'received'   => $receivedAmount,
                    'extra'      => $overpayment,
                    'is_guest'   => !($isRealUser ?? false),
                ]);
            }

            return [
                'matched'          => true,
                'deposit_id'       => $deposit->id,
                'deposit_reference'=> $deposit->payment_reference,
                'method'           => $method,
                'confidence'       => $confidence,
                'overpayment'      => $overpayment,
            ];
        });
    }

    private function manualReview(IncomingSms $sms, SmsDeposit $deposit, array $parsed, string $reason): array
    {
        $deposit->update(['status' => 'manual_review']);
        $sms->update(['processing_status' => 'manual_review']);

        return ['matched' => false, 'reason' => $reason, 'manual_review' => true, 'deposit_id' => $deposit->id];
    }

    /** Execute the deposit purpose after successful payment match */
    private function fulfillDeposit(SmsDeposit $deposit): void
    {
        switch ($deposit->purpose) {

            case 'wallet_topup':
                if ($deposit->user_id) {
                    $wallet = app(WalletService::class);
                    $wallet->credit(
                        $deposit->user_id,
                        (float) $deposit->received_amount,
                        'TOPUP',
                        null,
                        'sms_auto_' . $deposit->payment_reference
                    );
                    \App\Models\Notification::send(
                        $deposit->user_id,
                        'Wallet topped up ✓',
                        '₵' . number_format($deposit->received_amount, 2) . ' automatically detected and credited to your wallet.',
                        'success'
                    );
                }
                break;

            case 'bundle_purchase':
            case 'agent_bundle_purchase':
                $this->fulfillBundlePurchase($deposit);
                break;
        }
    }

    /** Dispatch bundle to DataSika after SMS payment is confirmed */
    private function fulfillBundlePurchase(SmsDeposit $deposit): void
    {
        $meta = $deposit->purpose_meta ?? [];
        $productId = $meta['product_id'] ?? null;
        $recipient = $meta['recipient'] ?? null;
        $agentId = $meta['agent_id'] ?? null;

        if (! $productId || ! $recipient) {
            Log::error('fulfillBundlePurchase: missing product_id or recipient', ['deposit_id' => $deposit->id]);
            return;
        }

        $product = \App\Models\Product::find($productId);
        if (! $product) return;

        $userId = $deposit->user_id;
        $amountCharged = (float) $deposit->received_amount;
        $costAmount = (float) ($product->cost_price ?? $product->sell_price);

        // Create the order
        $order = \App\Models\Order::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'idempotency_key' => 'sms_' . $deposit->payment_reference,
            'user_id' => $userId,
            'product_id' => $product->id,
            'recipient' => $recipient,
            'payment_reference' => $deposit->payment_reference,
            'payment_method' => 'manual_sms',
            'amount_charged' => $amountCharged,
            'cost_amount' => $costAmount,
            'status' => 'PENDING',
        ]);

        // Dispatch to DataSika
        try {
            $ds = app(\App\Services\DataSikaClient::class);
            $result = $ds->buyData($product->data_sika_id, $recipient, 'sms_' . $deposit->payment_reference);
            $order->update(['data_sika_order_id' => $result['order_id']]);
        } catch (\Exception $e) {
            Log::error('fulfillBundlePurchase: DataSika error', ['error' => $e->getMessage()]);
            $order->update(['status' => 'FAILED', 'failure_reason' => $e->getMessage()]);
        }

        // Credit agent commission if agent shop purchase
        if ($agentId) {
            $agentPrice = (float) ($meta['agent_price'] ?? $product->agent_price ?? $product->cost_price);
            $commission = round($amountCharged - $agentPrice, 2);
            if ($commission > 0) {
                $wallet = app(WalletService::class);
                $wallet->credit($agentId, $commission, 'AGENT_COMMISSION', $order->id, 'sms_commission_' . $deposit->payment_reference);
                \App\Models\Notification::send($agentId, 'Commission earned ✓', '₵' . number_format($commission, 2) . " commission for order to {$recipient}.", 'success');
            }
        }

        // Notify customer
        if ($userId) {
            \App\Models\Notification::send(
                $userId,
                'Payment received — bundle queued ✓',
                "{$product->network} " . intval($product->bundle_gb) . "GB for {$recipient} is being processed.",
                'success'
            );
        }

        Log::info('fulfillBundlePurchase: order created and dispatched', [
            'order_id' => $order->id,
            'deposit_id' => $deposit->id,
        ]);
    }

    private function amountsMatch(float $received, float $expected): bool
    {
        if ($expected == 0) return false;
        $diff = abs($received - $expected) / $expected;
        return $diff <= $this->amountTolerance;
    }
}