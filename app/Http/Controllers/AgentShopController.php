<?php
namespace App\Http\Controllers;

use App\Models\AgentResellPrice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DataSikaClient;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AgentShopController extends Controller
{
    /**
     * Public storefront showing the agent's resell prices.
     * No login required — anyone with the link can view and buy.
     */
    public function show(string $code)
    {
        $agent = User::where('agent_code', $code)->firstOrFail();

        if (! $agent->isActiveAgent()) {
            return view('agent.shop-offline', ['agentName' => $agent->name]);
        }

        $products = Product::where('is_available', true)
            ->orderByRaw("CASE network WHEN 'MTN' THEN 1 WHEN 'Telecel' THEN 2 WHEN 'AirtelTigo' THEN 3 ELSE 4 END")
            ->orderBy('bundle_gb')
            ->get();

        $resellPrices = AgentResellPrice::where('user_id', $agent->id)->pluck('resell_price', 'product_id');

        // Only show products that have a resell price set
        $available = $products->filter(fn ($p) => $resellPrices->has($p->id));

        return view('agent.shop', [
            'agent' => $agent,
            'products' => $available,
            'resellPrices' => $resellPrices,
            'paystackEnabled' => \App\Models\CryptoSettings::current()->paystack_enabled ?? true,
        ]);
    }

    /**
     * Customer buys a bundle from agent's shop via Paystack.
     * Flow: customer pays agent's resell price -> Paystack -> platform dispatches bundle
     * -> agent's wallet is debited at agent_price -> commission (resell - agent_price) stays in wallet
     */
    public function buy(Request $request, string $code)
    {
        $settings = \App\Models\CryptoSettings::current();
        $paystackEnabled = $settings->paystack_enabled ?? true;

        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient' => 'required|regex:/^0\d{9}$/',
            'customer_email' => $paystackEnabled ? 'required|email' : 'nullable|email',
            'customer_name' => 'nullable|string|max:100',
            'payment_reference' => $paystackEnabled ? 'nullable' : 'required|string|max:100',
        ]);

        $agent = User::where('agent_code', $code)->firstOrFail();
        if (! $agent->isActiveAgent()) {
            return back()->with('error', 'This shop is currently offline.');
        }

        $product = Product::findOrFail($data['product_id']);
        $resellPrice = AgentResellPrice::where('user_id', $agent->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $resellPrice) {
            return back()->with('error', 'This bundle is not available.');
        }

        // Manual payment mode
        if (! $paystackEnabled) {
            $order = \App\Models\Order::create([
                'id' => (string) Str::uuid(),
                'idempotency_key' => 'agent_manual_' . $data['payment_reference'],
                'user_id' => $agent->id,
                'product_id' => $product->id,
                'recipient' => $data['recipient'],
                'payment_reference' => $data['payment_reference'],
                'payment_method' => 'manual',
                'amount_charged' => (float) $resellPrice->resell_price,
                'cost_amount' => (float) ($product->agent_price ?? $product->cost_price),
                'status' => 'AWAITING_APPROVAL',
            ]);

            // Notify agent
            \App\Models\Notification::send(
                $agent->id,
                'New manual payment on your shop',
                "Customer submitted ref {$data['payment_reference']} for {$product->network} {$product->bundle_gb}GB to {$data['recipient']}. Verify and approve from admin.",
                'warning'
            );

            return redirect("/shop/{$code}/track?phone={$data['recipient']}")->with('success', 'Payment reference submitted! The vendor will confirm and send your bundle shortly.');
        }

        $price = (float) $resellPrice->resell_price;
        $chargeAmount = round($price * 1.02, 2); // 2% service charge
        $ref = 'agent_' . $code . '_' . Str::random(12);

        $paystackSecret = config('services.paystack.secret_key');
        $response = Http::withToken($paystackSecret)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $data['customer_email'],
                'amount' => (int) round($chargeAmount * 100),
                'reference' => $ref,
                'callback_url' => url("/shop/{$code}/callback"),
                'metadata' => [
                    'type' => 'agent_sale',
                    'agent_id' => $agent->id,
                    'agent_code' => $code,
                    'product_id' => $product->id,
                    'recipient' => $data['recipient'],
                    'resell_price' => $price,
                    'agent_price' => (float) ($product->agent_price ?? $product->sell_price),
                    'cost_price' => (float) $product->cost_price,
                    'customer_email' => $data['customer_email'],
                    'customer_name' => $data['customer_name'] ?? 'Customer',
                ],
            ]);

        $result = $response->json();
        if (! ($result['status'] ?? false)) {
            return back()->with('error', 'Could not connect to payment. Try again.');
        }

        return redirect($result['data']['authorization_url']);
    }

    /**
     * Paystack redirects here after customer pays on agent's shop.
     */
    public function callback(Request $request, string $code)
    {
        $ref = $request->query('reference') ?? $request->query('trxref');
        if (! $ref) {
            return redirect("/shop/{$code}")->with('error', 'Invalid payment.');
        }

        $paystackSecret = config('services.paystack.secret_key');
        $response = Http::withToken($paystackSecret)
            ->get("https://api.paystack.co/transaction/verify/{$ref}");

        $result = $response->json();
        if (! ($result['status'] ?? false) || ($result['data']['status'] ?? '') !== 'success') {
            return redirect("/shop/{$code}")->with('error', 'Payment was not successful.');
        }

        $meta = $result['data']['metadata'] ?? [];

        // Idempotency
        if (Order::where('idempotency_key', 'agent-' . $ref)->exists()) {
            return redirect("/shop/{$code}")->with('success', 'Order already processed!');
        }

        return $this->processAgentSale($meta, $ref, $code);
    }

    private function processAgentSale(array $meta, string $ref, string $code)
    {
        $wallet = app(WalletService::class);
        $ds = app(DataSikaClient::class);

        $agentId = (int) ($meta['agent_id'] ?? 0);
        $productId = $meta['product_id'] ?? '';
        $recipient = $meta['recipient'] ?? '';
        $resellPrice = (float) ($meta['resell_price'] ?? 0);
        $agentPrice = (float) ($meta['agent_price'] ?? 0);
        $costPrice = (float) ($meta['cost_price'] ?? 0);

        $product = Product::find($productId);
        $agent = User::find($agentId);
        if (! $product || ! $agent) {
            return redirect("/shop/{$code}")->with('error', 'Invalid order data.');
        }

        $orderId = (string) Str::uuid();

        $order = Order::create([
            'id' => $orderId,
            'idempotency_key' => 'agent-' . $ref,
            'user_id' => $agent->id,
            'product_id' => $product->id,
            'recipient' => $recipient,
            'amount_charged' => $resellPrice,
            'cost_amount' => $costPrice,
            'status' => 'PENDING',
        ]);

        // Agent earns commission: resell_price - agent_price
        // Customer paid the platform via Paystack, so nothing is debited from the agent.
        // Only the commission is credited to the agent's wallet.
        $commission = round($resellPrice - $agentPrice, 2);
        if ($commission > 0) {
            $wallet->credit($agentId, $commission, 'AGENT_COMMISSION', $order->id, $ref);
        }

        try {
            $result = $ds->buyData($product->data_sika_id, $recipient, 'agent-' . $ref);
            $order->update(['data_sika_order_id' => $result['order_id']]);
        } catch (\Exception $e) {
            // Dispatch failed — reverse the commission
            if ($commission > 0) {
                $wallet->debit($agentId, $commission, 'COMMISSION_REVERSED', null, 'failed-' . $ref);
            }
            $order->update(['status' => 'FAILED', 'failure_reason' => $e->getMessage()]);
            return redirect("/shop/{$code}")->with('error', 'Bundle dispatch failed. Please contact the vendor.');
        }

        // On mock mode only, auto-deliver. On live, cron polls DataSika for real status.
        if (config('services.datasika.api_key') === 'mock' || empty(config('services.datasika.api_key'))) {
            $order->update(['status' => 'DELIVERED']);
        }

        return redirect("/shop/{$code}/track?phone={$recipient}")->with('success', 'Payment received! Your bundle is being processed.');
    }

    /** Track order lookup page — search by phone number */
    public function trackLookup(Request $request, string $code)
    {
        $agent = User::where('agent_code', $code)->firstOrFail();
        $phone = $request->query('phone');
        $orders = null;

        if ($phone) {
            $orders = Order::with('product')
                ->where('user_id', $agent->id)
                ->where('recipient', $phone)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->appends(['phone' => $phone]);
        }

        return view('agent.shop-track-lookup', [
            'agent' => $agent,
            'phone' => $phone,
            'orders' => $orders,
        ]);
    }

    /** Public order tracking page for agent shop customers */
    public function track(string $code, string $orderId)
    {
        $agent = User::where('agent_code', $code)->firstOrFail();
        $order = Order::with('product')->where('id', $orderId)->where('user_id', $agent->id)->firstOrFail();

        return view('agent.shop-track', [
            'agent' => $agent,
            'order' => $order,
        ]);
    }

    /** JSON status endpoint for polling */
    public function trackStatus(string $code, string $orderId)
    {
        $agent = User::where('agent_code', $code)->firstOrFail();
        $order = Order::where('id', $orderId)->where('user_id', $agent->id)->firstOrFail();

        return response()->json([
            'status' => $order->status,
            'updated_at' => $order->updated_at->diffForHumans(),
        ]);
    }
}