<?php
namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DataSikaClient;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ManualOrderController extends Controller
{
    /** Customer submits manual payment reference */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient' => 'required|regex:/^0\d{9}$/',
            'payment_reference' => 'required|string|max:100',
        ]);

        $product = Product::findOrFail($data['product_id']);
        if (! $product->is_available) {
            return back()->with('error', 'This bundle is currently unavailable.');
        }

        // Find or create guest user
        $guestUser = User::firstOrCreate(
            ['phone' => '0000000000'],
            ['name' => 'Guest Orders', 'password' => bcrypt(Str::random(32)), 'role' => 'CUSTOMER']
        );

        $order = Order::create([
            'id' => (string) Str::uuid(),
            'idempotency_key' => 'manual_' . $data['payment_reference'],
            'user_id' => $guestUser->id,
            'product_id' => $product->id,
            'recipient' => $data['recipient'],
            'payment_reference' => $data['payment_reference'],
            'payment_method' => 'manual',
            'amount_charged' => (float) $product->sell_price,
            'cost_amount' => (float) $product->cost_price,
            'status' => 'AWAITING_APPROVAL',
        ]);

        // Notify all admins
        $admins = User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            Notification::send(
                $admin->id,
                'New manual payment',
                "Manual payment for {$product->network} {$product->bundle_gb}GB to {$data['recipient']}. Ref: {$data['payment_reference']}",
                'warning',
                '/admin'
            );
        }

        return redirect("/track?phone={$data['recipient']}")->with('success', 'Your payment reference has been submitted. We will confirm and send your bundle shortly.');
    }

    /** Admin approves manual order — triggers DataSika */
    public function approve(string $id, DataSikaClient $ds, WalletService $wallet)
    {
        $order = Order::with('product')->findOrFail($id);

        if ($order->status !== 'AWAITING_APPROVAL') {
            return back()->with('error', 'This order has already been processed.');
        }

        try {
            $result = $ds->buyData($order->product->data_sika_id, $order->recipient, 'manual-' . $order->id);
            $order->update([
                'data_sika_order_id' => $result['order_id'],
                'status' => 'PENDING',
            ]);
        } catch (\Exception $e) {
            return back()->with('error', 'DataSika error: ' . $e->getMessage());
        }

        // If it was an agent shop order, credit commission to the agent
        $agent = User::find($order->user_id);
        if ($agent && $agent->role === 'AGENT') {
            $agentPrice = (float) ($order->product->agent_price ?? $order->product->sell_price);
            $commission = round($order->amount_charged - $agentPrice, 2);
            if ($commission > 0) {
                $wallet->credit($agent->id, $commission, 'AGENT_COMMISSION', $order->id, 'manual_approved_' . $order->id);
            }
            Notification::send($agent->id, 'Order approved ✓', "Your customer's order for {$order->recipient} has been confirmed and is being processed.", 'success');
        }

        return back()->with('success', "Order approved and sent to DataSika for {$order->recipient}.");
    }

    /** Admin rejects manual order */
    public function reject(string $id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'AWAITING_APPROVAL') {
            return back()->with('error', 'This order has already been processed.');
        }

        $order->update(['status' => 'FAILED', 'failure_reason' => 'Payment reference rejected by admin.']);

        return back()->with('success', 'Order rejected.');
    }
}