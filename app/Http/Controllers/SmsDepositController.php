<?php
namespace App\Http\Controllers;

use App\Models\SmsDeposit;
use App\Models\SmsPaymentTransaction;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Illuminate\Http\Request;

class SmsDepositController extends Controller
{
    /** Show topup form with payment reference */
    public function create()
    {
        $user = auth()->user();
        $deposit = SmsDeposit::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        $admin = \App\Models\User::where('role', 'ADMIN')->first();
        return view('wallet-topup', compact('deposit', 'admin'));
    }

    /** Create a new topup deposit request */
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user = $request->user();

        // Cancel any existing pending deposit
        SmsDeposit::where('user_id', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $deposit = SmsDeposit::create([
            'user_id' => $user->id,
            'payment_reference' => SmsDeposit::generateReference(),
            'expected_amount' => $data['amount'],
            'purpose' => 'wallet_topup',
            'payer_phone' => $user->phone,
            'expires_at' => now()->addHours(2), // expires in 2 hours
        ]);

        return redirect()->route('topup.status', $deposit->id);
    }

    /** Show topup status — polls until paid */
    public function status(int $id)
    {
        $deposit = SmsDeposit::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $admin = \App\Models\User::where('role', 'ADMIN')->first();

        return view('wallet-topup-status', compact('deposit', 'admin'));
    }

    /** JSON endpoint for polling */
    public function statusJson(int $id)
    {
        $deposit = SmsDeposit::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'status' => $deposit->status,
            'received_amount' => $deposit->received_amount,
        ]);
    }

    /** Admin: manually approve a manual_review deposit */
    public function adminApprove(int $id, WalletService $wallet)
    {
        $deposit = SmsDeposit::with('user')->findOrFail($id);

        if (! in_array($deposit->status, ['manual_review', 'pending'])) {
            return back()->with('error', 'This deposit cannot be approved.');
        }

        $wallet->credit(
            $deposit->user_id,
            (float) ($deposit->received_amount ?? $deposit->expected_amount),
            'TOPUP',
            null,
            'manual_approved_' . $deposit->id
        );

        $deposit->update([
            'status' => 'completed',
            'received_amount' => $deposit->received_amount ?? $deposit->expected_amount,
            'completed_at' => now(),
        ]);

        \App\Models\Notification::send(
            $deposit->user_id,
            'Wallet topped up ✓',
            '₵' . number_format($deposit->received_amount, 2) . ' has been added to your wallet.',
            'success'
        );

        return back()->with('success', 'Deposit approved and wallet credited.');
    }
}
