<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\WalletLedger;
use App\Services\DataSikaClient;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaystackController extends Controller
{
    private function secretKey(): string
    {
        return config('services.paystack.secret_key', '');
    }

    /**
     * Initialize a Paystack transaction for wallet topup.
     */
    public function initializeTopup(Request $request)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:1|max:50000']);
        $user = $request->user();
        $ref = 'topup_' . Str::random(16);
        $amount = (float) $data['amount'];
        $chargeAmount = round($amount * 1.02, 2); // 2% service charge

        $response = Http::withToken($this->secretKey())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email ?? $user->phone . '@customer.freedomdata.co',
                'amount' => (int) round($chargeAmount * 100), // customer pays base + 2%
                'reference' => $ref,
                'callback_url' => url('/payment/callback'),
                'metadata' => [
                    'type' => 'topup',
                    'user_id' => $user->id,
                    'amount' => $amount, // wallet gets the base amount only
                ],
            ]);

        $result = $response->json();
        if (! ($result['status'] ?? false)) {
            return back()->with('error', 'Could not connect to Paystack. Try again.');
        }

        return redirect($result['data']['authorization_url']);
    }

    /**
     * Initialize a Paystack transaction for direct bundle purchase (skip wallet).
     */
    public function initializeDirectPay(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient' => 'required|regex:/^0\d{9}$/',
        ]);

        $user = $request->user();
        $product = Product::findOrFail($data['product_id']);

        if (! $product->is_available) {
            return back()->with('error', 'This bundle is currently unavailable.');
        }

        $isAgent = $user->isActiveAgent();
        $price = ($isAgent && $product->agent_price) ? (float) $product->agent_price : (float) $product->sell_price;
        $chargeAmount = round($price * 1.02, 2); // 2% service charge
        $ref = 'direct_' . Str::random(16);

        $response = Http::withToken($this->secretKey())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email ?? $user->phone . '@customer.freedomdata.co',
                'amount' => (int) round($chargeAmount * 100), // customer pays base + 2%
                'reference' => $ref,
                'callback_url' => url('/payment/callback'),
                'metadata' => [
                    'type' => 'direct_buy',
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'recipient' => $data['recipient'],
                    'amount' => $price, // order records base price
                    'cost' => (float) $product->cost_price,
                ],
            ]);

        $result = $response->json();
        if (! ($result['status'] ?? false)) {
            return back()->with('error', 'Could not connect to Paystack. Try again.');
        }

        return redirect($result['data']['authorization_url']);
    }

    /**
     * Guest checkout — no account needed.
     */
    public function initializeGuestPay(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient' => 'required|regex:/^0\d{9}$/',
            'email' => 'required|email',
        ]);

        $product = Product::findOrFail($data['product_id']);
        if (! $product->is_available) {
            return back()->with('error', 'This bundle is currently unavailable.');
        }

        $price = (float) $product->sell_price;
        $chargeAmount = round($price * 1.02, 2); // 2% service charge
        $ref = 'guest_' . Str::random(16);

        $response = Http::withToken($this->secretKey())
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $data['email'],
                'amount' => (int) round($chargeAmount * 100), // customer pays base + 2%
                'reference' => $ref,
                'callback_url' => url('/payment/callback'),
                'metadata' => [
                    'type' => 'guest_buy',
                    'product_id' => $product->id,
                    'recipient' => $data['recipient'],
                    'amount' => $price, // order records base price
                    'cost' => (float) $product->cost_price,
                ],
            ]);

        $result = $response->json();
        if (! ($result['status'] ?? false)) {
            return back()->with('error', 'Could not connect to payment. Try again.');
        }

        return redirect($result['data']['authorization_url']);
    }

    /**
     * Paystack redirects here after payment. Verify and process.
     */
    public function callback(Request $request)
    {
        $ref = $request->query('reference') ?? $request->query('trxref');
        if (! $ref) {
            return redirect('/')->with('error', 'Invalid payment callback.');
        }

        $response = Http::withToken($this->secretKey())
            ->get("https://api.paystack.co/transaction/verify/{$ref}");

        $result = $response->json();
        if (! ($result['status'] ?? false) || ($result['data']['status'] ?? '') !== 'success') {
            return redirect('/')->with('error', 'Payment was not successful.');
        }

        $meta = $result['data']['metadata'] ?? [];
        $type = $meta['type'] ?? '';

        // Idempotency
        if (WalletLedger::where('reference', $ref)->exists() || Order::where('idempotency_key', 'guest-' . $ref)->exists()) {
            return redirect('/wallet')->with('success', 'Payment already processed.');
        }

        if ($type === 'topup') {
            return $this->processTopup($meta, $ref);
        } elseif ($type === 'direct_buy') {
            return $this->processDirectBuy($meta, $ref);
        } elseif ($type === 'guest_buy') {
            return $this->processGuestBuy($meta, $ref);
        }

        return redirect('/')->with('error', 'Unknown payment type.');
    }

    /**
     * Paystack webhook — handles cases where the customer closes the browser.
     */
    public function webhook(Request $request)
    {
        $secret = $this->secretKey();
        $signature = $request->header('x-paystack-signature');
        $body = $request->getContent();

        $expected = hash_hmac('sha512', $body, $secret);
        if (! hash_equals($expected, (string) $signature)) {
            Log::warning('Paystack webhook: invalid signature');
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $event = json_decode($body, true);
        if (($event['event'] ?? '') !== 'charge.success') {
            return response()->json(['ok' => true]);
        }

        $data = $event['data'] ?? [];
        $ref = $data['reference'] ?? '';
        $meta = $data['metadata'] ?? [];
        $type = $meta['type'] ?? '';

        // Idempotency
        if (WalletLedger::where('reference', $ref)->exists() || Order::where('idempotency_key', 'guest-' . $ref)->exists()) {
            return response()->json(['ok' => true]);
        }

        if ($type === 'topup') {
            $this->processTopup($meta, $ref);
        } elseif ($type === 'direct_buy') {
            $this->processDirectBuy($meta, $ref);
        } elseif ($type === 'guest_buy') {
            $this->processGuestBuy($meta, $ref);
        }

        return response()->json(['ok' => true]);
    }

    private function processTopup(array $meta, string $ref)
    {
        $wallet = app(WalletService::class);
        $userId = (int) ($meta['user_id'] ?? 0);
        $amount = (float) ($meta['amount'] ?? 0);

        if (! $userId || $amount <= 0) {
            return redirect('/wallet')->with('error', 'Invalid topup data.');
        }

        $wallet->credit($userId, $amount, 'TOPUP', null, $ref);
        return redirect('/wallet')->with('success', 'GHS ' . number_format($amount, 2) . ' added to your wallet.');
    }

    private function processDirectBuy(array $meta, string $ref)
    {
        $wallet = app(WalletService::class);
        $ds = app(DataSikaClient::class);

        $userId = (int) ($meta['user_id'] ?? 0);
        $productId = $meta['product_id'] ?? '';
        $recipient = $meta['recipient'] ?? '';
        $amount = (float) ($meta['amount'] ?? 0);
        $cost = (float) ($meta['cost'] ?? 0);

        $product = Product::find($productId);
        if (! $product || ! $userId) {
            return redirect('/')->with('error', 'Invalid payment data.');
        }

        $orderId = (string) Str::uuid();

        $order = Order::create([
            'id' => $orderId,
            'idempotency_key' => 'order-' . $ref,
            'user_id' => $userId,
            'product_id' => $product->id,
            'recipient' => $recipient,
            'amount_charged' => $amount,
            'cost_amount' => $cost,
            'status' => 'PENDING',
        ]);

        // Credit then debit so the ledger has a record
        $wallet->credit($userId, $amount, 'PAYSTACK_DIRECT', null, $ref);
        $wallet->debit($userId, $amount, 'PURCHASE', $order->id, $ref . '_buy');

        try {
            $result = $ds->buyData($product->data_sika_id, $recipient, 'order-' . $ref);
            $order->update(['data_sika_order_id' => $result['order_id']]);
        } catch (\Exception $e) {
            $wallet->credit($userId, $amount, 'REFUND', null, 'refund_' . $ref);
            $order->update(['status' => 'FAILED', 'failure_reason' => $e->getMessage()]);
            return redirect("/track?phone={$recipient}")->with('error', 'Bundle dispatch failed. Payment refunded to your wallet.');
        }

        if (empty(config('services.datasika.api_key')) || config('services.datasika.api_key') === 'mock') {
            $order->update(['status' => 'DELIVERED']);
        }

        return redirect("/track?phone={$recipient}")->with('success', 'Payment received! Order placed.');
    }

    private function processGuestBuy(array $meta, string $ref)
    {
        $ds = app(DataSikaClient::class);
        $productId = $meta['product_id'] ?? '';
        $recipient = $meta['recipient'] ?? '';
        $amount = (float) ($meta['amount'] ?? 0);
        $cost = (float) ($meta['cost'] ?? 0);

        $product = Product::find($productId);
        if (! $product) {
            return redirect('/')->with('error', 'Invalid order data.');
        }

        $orderId = (string) Str::uuid();

        $guestUser = \App\Models\User::firstOrCreate(
            ['phone' => '0000000000'],
            ['name' => 'Guest Orders', 'password' => bcrypt(Str::random(32)), 'role' => 'CUSTOMER']
        );

        $order = Order::create([
            'id' => $orderId,
            'idempotency_key' => 'guest-' . $ref,
            'user_id' => $guestUser->id,
            'product_id' => $product->id,
            'recipient' => $recipient,
            'amount_charged' => $amount,
            'cost_amount' => $cost,
            'status' => 'PENDING',
        ]);

        try {
            $result = $ds->buyData($product->data_sika_id, $recipient, 'guest-' . $ref);
            $order->update(['data_sika_order_id' => $result['order_id']]);
        } catch (\Exception $e) {
            $order->update(['status' => 'FAILED', 'failure_reason' => $e->getMessage()]);
            return redirect('/')->with('error', 'Bundle dispatch failed. Contact support for a refund.');
        }

        if (empty(config('services.datasika.api_key')) || config('services.datasika.api_key') === 'mock') {
            $order->update(['status' => 'DELIVERED']);
        }

        return redirect("/track?phone={$recipient}")->with('success', 'Payment received! Your bundle is being sent to ' . $recipient . '.');
    }
}
