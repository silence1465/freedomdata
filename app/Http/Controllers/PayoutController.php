<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\PayoutRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    /** Payout page — user sees their balance, picks amount + network */
    public function index()
    {
        $user = auth()->user();
        $pendingPayout = PayoutRequest::where('user_id', $user->id)->where('status', 'PENDING')->first();
        $payouts = PayoutRequest::where('user_id', $user->id)->orderByDesc('created_at')->limit(20)->get();

        return view('payout', compact('user', 'pendingPayout', 'payouts'));
    }

    /** Submit payout — name and number auto-filled from profile */
    public function store(Request $request, WalletService $wallet)
    {
        $user = $request->user();
        $data = $request->validate([
            'amount' => 'required|numeric|min:10',
            'provider' => 'required|in:MTN MoMo,Telecel Cash,AirtelTigo Money',
        ]);

        if ($data['amount'] > (float) $user->wallet_balance) {
            return back()->with('error', 'Payout amount exceeds your wallet balance.');
        }

        if (PayoutRequest::where('user_id', $user->id)->where('status', 'PENDING')->exists()) {
            return back()->with('error', 'You already have a pending payout. Wait for it to be processed.');
        }

        try {
            $wallet->debit($user->id, (float) $data['amount'], 'PAYOUT_HOLD', null, 'payout_request');
        } catch (\Exception $e) {
            return back()->with('error', 'Insufficient balance.');
        }

        PayoutRequest::create([
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'method' => 'momo',
            'account_name' => $user->name,
            'account_number' => $user->phone,
            'provider' => $data['provider'],
        ]);

        Notification::send($user->id, 'Payout requested', "Your payout of ₵{$data['amount']} is being processed.", 'info', '/payout');

        $admins = \App\Models\User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            Notification::send($admin->id, 'New payout request', "{$user->name} ({$user->phone}) requested ₵{$data['amount']} payout via {$data['provider']}.", 'warning', '/admin/payouts');
        }

        return back()->with('success', 'Payout request submitted! You will be notified when it is processed.');
    }

    /** Admin: list all payout requests */
    public function adminIndex()
    {
        $payouts = PayoutRequest::with('user')->orderByDesc('created_at')->paginate(20);
        $pendingCount = PayoutRequest::where('status', 'PENDING')->count();
        return view('admin.payouts', compact('payouts', 'pendingCount'));
    }

    /** Admin: mark as paid */
    public function approve(int $id)
    {
        $payout = PayoutRequest::findOrFail($id);
        if (! $payout->isPending()) return back()->with('error', 'Already processed.');

        $payout->update(['status' => 'PAID']);
        Notification::send($payout->user_id, 'Payout sent!', "₵{$payout->amount} has been sent to your {$payout->provider} ({$payout->account_number}).", 'success', '/payout');

        return back()->with('success', "₵{$payout->amount} marked as paid.");
    }

    /** Admin: reject and refund */
    public function reject(Request $request, int $id, WalletService $wallet)
    {
        $payout = PayoutRequest::findOrFail($id);
        if (! $payout->isPending()) return back()->with('error', 'Already processed.');

        $payout->update(['status' => 'REJECTED', 'admin_notes' => $request->input('admin_notes', 'Rejected')]);
        $wallet->credit($payout->user_id, (float) $payout->amount, 'PAYOUT_REFUND', null, "payout_rejected_{$payout->id}");
        Notification::send($payout->user_id, 'Payout rejected', "Your payout of ₵{$payout->amount} was rejected. Amount returned to your wallet.", 'alert', '/payout');

        return back()->with('success', 'Rejected and refunded.');
    }
}
