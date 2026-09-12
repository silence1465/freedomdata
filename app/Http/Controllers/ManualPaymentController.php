<?php
namespace App\Http\Controllers;

use App\Models\CryptoSettings;
use App\Models\Product;
use App\Models\SmsDeposit;
use App\Models\User;
use Illuminate\Http\Request;

class ManualPaymentController extends Controller
{
    /** Storefront: logged-in customer initiates manual bundle payment */
    public function initStorefront(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient'  => 'required|regex:/^0\d{9}$/',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $user    = $request->user();
        $isAgent = $user->isActiveAgent();

        $price = $product->sell_price;
        if ($isAgent) {
            $special = \App\Models\AgentSpecialPrice::where('user_id', $user->id)->where('product_id', $product->id)->first();
            $price = $special ? $special->special_price : ($product->agent_price ?? $product->sell_price);
        }

        $deposit = $this->createDeposit(
            userId: $user->id,
            amount: (float) $price,
            purpose: 'bundle_purchase',
            meta: ['product_id' => $product->id, 'recipient' => $data['recipient'], 'network' => $product->network, 'bundle_gb' => $product->bundle_gb]
        );

        return redirect()->route('payment.show', $deposit->payment_reference);
    }

    /** Guest: initiates manual bundle payment */
    public function initGuest(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient'  => 'required|regex:/^0\d{9}$/',
        ]);

        $product = Product::findOrFail($data['product_id']);

        $deposit = $this->createDeposit(
            userId: null,
            amount: (float) $product->sell_price,
            purpose: 'bundle_purchase',
            meta: ['product_id' => $product->id, 'recipient' => $data['recipient'], 'network' => $product->network, 'bundle_gb' => $product->bundle_gb, 'is_guest' => true]
        );

        return redirect()->route('payment.show', $deposit->payment_reference);
    }

    /** Agent shop: customer initiates manual bundle payment */
    public function initAgentShop(Request $request, string $code)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient'  => 'required|regex:/^0\d{9}$/',
        ]);

        $agent   = User::where('agent_code', $code)->firstOrFail();
        if (! $agent->isActiveAgent()) return back()->with('error', 'This shop is currently offline.');

        $product = Product::findOrFail($data['product_id']);

        $resellPrice = \App\Models\AgentResellPrice::where('user_id', $agent->id)
            ->where('product_id', $product->id)->first();

        if (! $resellPrice) return back()->with('error', 'This bundle is not available in this shop.');

        $deposit = $this->createDeposit(
            userId: null,
            amount: (float) $resellPrice->resell_price,
            purpose: 'agent_bundle_purchase',
            meta: ['product_id' => $product->id, 'recipient' => $data['recipient'], 'agent_id' => $agent->id, 'agent_price' => $product->agent_price ?? $product->cost_price, 'network' => $product->network, 'bundle_gb' => $product->bundle_gb, 'agent_code' => $code]
        );

        return redirect()->route('payment.show', $deposit->payment_reference);
    }

    /** Show the payment page (GET) */
    public function show(string $reference)
    {
        $deposit = SmsDeposit::where('payment_reference', $reference)->firstOrFail();
        $meta    = $deposit->purpose_meta ?? [];
        $product = Product::find($meta['product_id'] ?? null);
        $recipient = $meta['recipient'] ?? '';
        $agentCode = $meta['agent_code'] ?? null;
        $agentShop = $deposit->purpose === 'agent_bundle_purchase';

        $admin = $agentShop
            ? User::find($meta['agent_id'] ?? null)
            : User::where('role', 'ADMIN')->first();

        return view('manual-payment', compact('deposit', 'product', 'admin', 'recipient', 'agentShop', 'agentCode'));
    }

    /** JSON status check for polling */
    public function depositStatus(string $reference)
    {
        $deposit = SmsDeposit::where('payment_reference', $reference)->firstOrFail();
        return response()->json(['status' => $deposit->status, 'reference' => $deposit->payment_reference]);
    }

    /** Customer reports wrong/missing reference — sends their Transaction ID to admin */
    public function reportWrongReference(Request $request)
    {
        $data = $request->validate([
            'deposit_reference' => 'required|string',
            'transaction_id'    => 'required|string|max:100',
            'mtn_reference'     => 'required|string|max:50',
            'expected_amount'   => 'required|numeric',
        ]);

        // Check if this transaction ID was already reported
        if (\App\Models\WalletTopupRequest::where('transaction_id', $data['transaction_id'])->exists()) {
            return back()->with('success', 'This transaction was already submitted. Admin will verify and process it shortly.');
        }

        $deposit = SmsDeposit::where('payment_reference', $data['deposit_reference'])->first();

        $admins = User::where('role', 'ADMIN')->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::send(
                $admin->id,
                'Wrong reference reported',
                "Customer forgot ref {$data['deposit_reference']} (₵{$data['expected_amount']}). TxnID: {$data['transaction_id']} | MTN Ref: {$data['mtn_reference']}. Check MoMo and approve from /admin/agents.",
                'warning',
                '/admin/agents'
            );
        }

        \App\Models\WalletTopupRequest::create([
            'user_id'        => $deposit?->user_id ?? null,
            'amount'         => $data['expected_amount'],
            'transaction_id' => $data['transaction_id'],
            'mtn_reference'  => $data['mtn_reference'],
            'sender_name'    => 'Ref: ' . $data['deposit_reference'],
            'status'         => 'PENDING',
        ]);

        return back()->with('success', 'Transaction details sent to admin. We will verify and process your order shortly.');
    }

    private function createDeposit(?int $userId, float $amount, string $purpose, array $meta): SmsDeposit
    {
        return SmsDeposit::create([
            'user_id'           => $userId,
            'payment_reference' => SmsDeposit::generateReference(),
            'expected_amount'   => $amount,
            'purpose'           => $purpose,
            'purpose_meta'      => $meta,
            'expires_at'        => now()->addHours(2),
        ]);
    }
}