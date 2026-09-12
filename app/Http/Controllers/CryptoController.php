<?php
namespace App\Http\Controllers;

use App\Models\CryptoOrder;
use App\Models\CryptoSettings;
use App\Models\WalletLedger;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CryptoController extends Controller
{
    public function index()
    {
        $rate = CryptoSettings::current();
        $orders = auth()->check() ? CryptoOrder::where('user_id', auth()->id())->orderByDesc('created_at')->limit(20)->get() : collect();
        return view('crypto', compact('rate', 'orders'));
    }

    public function store(Request $request, WalletService $wallet)
    {
        $settings = CryptoSettings::current();
        if (! $settings->is_enabled) {
            return back()->with('error', 'The USDT desk is currently unavailable.');
        }

        $data = $request->validate([
            'type' => 'required|in:BUY,SELL',
            'ghs_amount' => "required|numeric|min:{$settings->min_ghs}|max:{$settings->max_ghs}",
            'wallet_address' => 'required_if:type,BUY|nullable|string',
            'tx_hash' => 'required_if:type,SELL|nullable|string',
            'wallet_network' => 'required|in:TRC20,ERC20,BEP20',
        ]);

        if ($data['type'] === 'BUY' && ! $settings->buy_enabled) {
            return back()->with('error', 'USDT buying is currently paused.');
        }
        if ($data['type'] === 'SELL' && ! $settings->sell_enabled) {
            return back()->with('error', 'USDT selling is currently paused.');
        }

        $rate = $data['type'] === 'BUY' ? (float) $settings->buy_rate : (float) $settings->sell_rate;
        $usdt = round($data['ghs_amount'] / $rate, 4);

        if ($data['type'] === 'BUY') {
            // Redirect to Paystack — customer pays GHS, then we create the order
            return $this->initiateBuyPayment($data, $usdt, $rate, $settings);
        }

        // SELL: customer already sent USDT, create order immediately
        $order = CryptoOrder::create([
            'id' => (string) Str::uuid(),
            'user_id' => auth()->id(),
            'type' => 'SELL',
            'ghs_amount' => $data['ghs_amount'],
            'usdt_amount' => $usdt,
            'rate_used' => $rate,
            'network' => $data['wallet_network'],
            'tx_hash' => $data['tx_hash'],
            'status' => 'AWAITING_CONFIRMATION',
        ]);

        return back()->with('success', 'Sell order submitted! We will verify your USDT and credit your wallet.');
    }

    private function initiateBuyPayment(array $data, float $usdt, float $rate, CryptoSettings $settings)
    {
        $user = auth()->user();
        $ref = 'crypto_buy_' . Str::random(16);
        $chargeAmount = round($data['ghs_amount'] * 1.02, 2); // 2% service charge

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email ?? $user->phone . '@customer.freedomdata.co',
                'amount' => (int) round($chargeAmount * 100),
                'reference' => $ref,
                'callback_url' => url('/crypto/callback'),
                'metadata' => [
                    'type' => 'crypto_buy',
                    'user_id' => $user->id,
                    'ghs_amount' => (float) $data['ghs_amount'],
                    'usdt_amount' => $usdt,
                    'rate_used' => $rate,
                    'wallet_address' => $data['wallet_address'],
                    'wallet_network' => $data['wallet_network'],
                ],
            ]);

        $result = $response->json();
        if (! ($result['status'] ?? false)) {
            return back()->with('error', 'Could not connect to payment. Try again.');
        }

        return redirect($result['data']['authorization_url']);
    }

    public function callback(Request $request)
    {
        $ref = $request->query('reference') ?? $request->query('trxref');
        if (! $ref) {
            return redirect('/crypto')->with('error', 'Invalid payment.');
        }

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$ref}");

        $result = $response->json();
        if (! ($result['status'] ?? false) || ($result['data']['status'] ?? '') !== 'success') {
            return redirect('/crypto')->with('error', 'Payment was not successful.');
        }

        $meta = $result['data']['metadata'] ?? [];

        // Idempotency
        if (CryptoOrder::where('tx_hash', $ref)->exists()) {
            return redirect('/crypto')->with('success', 'Order already processed.');
        }

        $order = CryptoOrder::create([
            'id' => (string) Str::uuid(),
            'user_id' => (int) $meta['user_id'],
            'type' => 'BUY',
            'ghs_amount' => $meta['ghs_amount'],
            'usdt_amount' => $meta['usdt_amount'],
            'rate_used' => $meta['rate_used'],
            'network' => $meta['wallet_network'],
            'customer_wallet_address' => $meta['wallet_address'],
            'tx_hash' => $ref,
            'status' => 'PENDING',
        ]);

        return redirect('/crypto')->with('success', 'Payment received! We will send ' . $meta['usdt_amount'] . ' USDT to your wallet.');
    }
}
