<?php
namespace App\Http\Controllers;

use App\Models\WalletLedger;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $entries = WalletLedger::where('user_id', $user->id)->orderByDesc('created_at')->limit(50)->get();
        $settings = \App\Models\CryptoSettings::current();
        $paystackEnabled = $settings->paystack_enabled ?? true;
        $admin = \App\Models\User::where('role', 'ADMIN')->first();
        return view('wallet', compact('user', 'entries', 'paystackEnabled', 'admin'));
    }

    /** Customer submits MoMo transaction details — auto-verified against received SMS */
    public function manualTopup(Request $request, WalletService $wallet)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'transaction_id' => 'required|string|max:100',
            'mtn_reference' => 'required|string|max:50',
            'sender_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // Prevent duplicate transaction ID
        if (\App\Models\WalletTopupRequest::where('transaction_id', $data['transaction_id'])->exists()) {
            return back()->with('error', 'This Transaction ID has already been submitted.');
        }

        $topupRequest = \App\Models\WalletTopupRequest::create([
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'transaction_id' => $data['transaction_id'],
            'mtn_reference' => $data['mtn_reference'],
            'sender_name' => $data['sender_name'] ?? null,
            'status' => 'PENDING',
        ]);

        // Try to auto-verify against SMS received by Android phone
        if ($this->tryAutoVerify($topupRequest, $wallet)) {
            return back()->with('success', '✓ Payment verified automatically! ₵' . number_format($data['amount'], 2) . ' has been added to your wallet.');
        }

        // SMS not matched yet — notify admin to verify manually
        $admins = \App\Models\User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::send(
                $admin->id,
                'Wallet topup request',
                "{$user->name} ({$user->phone}) sent ₵" . number_format($data['amount'], 2) . ". TxnID: {$data['transaction_id']} | Ref: {$data['mtn_reference']}",
                'warning',
                '/admin/agents'
            );
        }

        return back()->with('success', 'Payment details submitted. We are verifying your transaction and will credit your wallet shortly.');
    }

    /**
     * Try to auto-verify by matching transaction ID / reference against SMS already received by the Android phone.
     */
    private function tryAutoVerify(\App\Models\WalletTopupRequest $topup, WalletService $wallet): bool
    {
        // Find any SMS containing the transaction ID or MTN reference
        $sms = \App\Models\IncomingSms::where('processing_status', 'pending')
            ->where('created_at', '>=', now()->subHours(3))
            ->get()
            ->first(function ($sms) use ($topup) {
                return str_contains($sms->message, $topup->transaction_id)
                    || str_contains($sms->message, (string) $topup->mtn_reference);
            });

        if (! $sms) return false;

        // Verify amount from SMS matches what customer entered
        $parser = app(\App\Services\SmsParser::class);
        $parsed = $parser->parse($sms->sender, $sms->message);

        if (! $parsed['amount'] || abs($parsed['amount'] - (float) $topup->amount) > 0.01) {
            // Amount mismatch — manual review
            $topup->update(['status' => 'MANUAL_REVIEW', 'admin_notes' => "SMS found but amount differs. SMS: {$parsed['amount']}, Claimed: {$topup->amount}"]);
            $sms->update(['processing_status' => 'manual_review']);
            Log::warning('Topup auto-verify: amount mismatch', ['sms_amount' => $parsed['amount'], 'claimed' => $topup->amount]);
            return false;
        }

        // All good — credit wallet
        $wallet->credit($topup->user_id, (float) $topup->amount, 'TOPUP', null, 'sms_verified_' . $topup->transaction_id);
        $topup->update(['status' => 'APPROVED']);
        $sms->update(['processing_status' => 'matched', 'processed' => true]);

        \App\Models\Notification::send(
            $topup->user_id,
            'Wallet topped up ✓',
            '₵' . number_format($topup->amount, 2) . ' verified and credited to your wallet automatically.',
            'success'
        );

        Log::info('Topup auto-verified via SMS', ['user_id' => $topup->user_id, 'amount' => $topup->amount, 'txn_id' => $topup->transaction_id]);

        return true;
    }

    /** Admin manual credit — admin only */
    public function topup(Request $request, WalletService $wallet)
    {
        if (! $request->user()->isAdmin()) {
            return back()->with('error', 'Only admins can manually top up wallets.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:10000',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $targetUserId = $data['user_id'] ?? auth()->id();
        $wallet->credit((int) $targetUserId, (float) $data['amount'], 'TOPUP', null, 'admin_manual_topup');

        return back()->with('success', 'GHS ' . number_format($data['amount'], 2) . ' added.');
    }
}