<?php
namespace App\Http\Controllers;

use App\Models\CryptoSettings;
use App\Models\Order;
use App\Models\Product;
use App\Services\DataSikaClient;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    public function index()
    {
        $settings = CryptoSettings::current();
        $bundleEnabled = $settings->bundle_enabled ?? true;
        $paystackEnabled = $settings->paystack_enabled ?? true;
        $admin = \App\Models\User::where('role', 'ADMIN')->first();

        $products = Product::where('is_available', true)->orderBy('network')->orderBy('bundle_gb')->get();

        // Filter out exclusive products unless the user is an assigned agent
        $user = auth()->user();
        $products = $products->filter(function ($p) use ($user) {
            if (! $p->is_exclusive) return true;
            if (! $user || ! $user->isActiveAgent()) return false;
            return $user->exclusiveProducts()->where('product_id', $p->id)->exists();
        });

        $grouped = $products->groupBy('network');
        return view('storefront', compact('grouped', 'bundleEnabled', 'paystackEnabled', 'admin'));
    }

    public function buy(Request $request, WalletService $wallet, DataSikaClient $ds)
    {
        $settings = CryptoSettings::current();
        if (! ($settings->bundle_enabled ?? true)) {
            return back()->with('error', 'The data bundle service is currently paused.');
        }
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'recipient' => 'required|regex:/^0\d{9}$/',
        ]);

        $product = Product::findOrFail($data['product_id']);
        if (! $product->is_available) {
            return back()->with('error', 'This bundle is currently unavailable.');
        }

        $user = $request->user();
        $isAgent = $user->isActiveAgent();

        // Price priority: special price > agent price > sell price
        $sellPrice = (float) $product->sell_price;
        if ($isAgent) {
            $specialPrice = \App\Models\AgentSpecialPrice::where('user_id', $user->id)->where('product_id', $product->id)->first();
            if ($specialPrice) {
                $sellPrice = (float) $specialPrice->special_price;
            } elseif ($product->agent_price) {
                $sellPrice = (float) $product->agent_price;
            }
        }
        $orderId = (string) Str::uuid();
        $idempotencyKey = "order-{$orderId}";

        $order = Order::create([
            'id' => $orderId,
            'idempotency_key' => $idempotencyKey,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'recipient' => $data['recipient'],
            'amount_charged' => $sellPrice,
            'cost_amount' => $product->cost_price,
            'status' => 'PENDING',
        ]);

        try {
            $wallet->debit($user->id, $sellPrice, 'PURCHASE', $order->id);
        } catch (\Exception $e) {
            $order->delete();
            return back()->with('error', 'Insufficient wallet balance. Please top up first.');
        }

        try {
            $result = $ds->buyData($product->data_sika_id, $data['recipient'], $idempotencyKey);
            $order->update(['data_sika_order_id' => $result['order_id']]);
        } catch (\Exception $e) {
            $wallet->credit($user->id, $sellPrice, 'REFUND', $order->id, 'dispatch_failed');
            $order->update(['status' => 'FAILED', 'failure_reason' => $e->getMessage()]);
            return back()->with('error', 'Could not dispatch the bundle. Your wallet has been refunded.');
        }

        return redirect("/track?phone={$data['recipient']}")->with('success', 'Order placed!');
    }
}
