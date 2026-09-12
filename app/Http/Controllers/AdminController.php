<?php
namespace App\Http\Controllers;

use App\Models\AgentPlan;
use App\Models\CryptoOrder;
use App\Models\CryptoSettings;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $orders = Order::with('product', 'user')->orderByDesc('created_at')->limit(50)->get();
        $revenue = Order::where('status', 'DELIVERED')->sum('amount_charged');
        $cost = Order::where('status', 'DELIVERED')->sum('cost_amount');
        $totalOrders = Order::count();
        $cryptoPending   = CryptoOrder::whereIn('status', ['PENDING', 'AWAITING_CONFIRMATION'])->count();
        $totalAgents     = User::where('role', 'AGENT')->count();
        $manualPending   = \App\Models\Order::where('status', 'AWAITING_APPROVAL')->count();

        $settings        = CryptoSettings::current();
        $bundleEnabled   = $settings->bundle_enabled ?? true;
        $cryptoEnabled   = $settings->is_enabled;
        $buyEnabled      = $settings->buy_enabled ?? true;
        $sellEnabled     = $settings->sell_enabled ?? true;
        $paystackEnabled = $settings->paystack_enabled ?? true;

        return view('admin.dashboard', compact('orders', 'revenue', 'cost', 'totalOrders', 'cryptoPending', 'totalAgents', 'bundleEnabled', 'cryptoEnabled', 'buyEnabled', 'sellEnabled', 'paystackEnabled', 'manualPending'));
    }

    public function updateServices(Request $request)
    {
        $settings = CryptoSettings::current();
        $settings->update([
            'bundle_enabled' => $request->boolean('bundle_enabled'),
            'is_enabled' => $request->boolean('crypto_enabled'),
            'buy_enabled' => $request->boolean('buy_enabled'),
            'sell_enabled' => $request->boolean('sell_enabled'),
        ]);
        return back()->with('success', 'Service settings updated.');
    }

    public function pricing()
    {
        $products = Product::orderBy('network')->orderBy('bundle_gb')->get();
        return view('admin.pricing', compact('products'));
    }

    public function updatePrice(Request $request, string $id)
    {
        $data = $request->validate([
            'sell_price' => 'required|numeric|min:0.01',
            'agent_price' => 'nullable|numeric|min:0',
        ]);

        Product::findOrFail($id)->update($data);
        return back()->with('success', 'Price updated.');
    }

    public function cryptoDesk()
    {
        $settings = CryptoSettings::current();
        $orders = CryptoOrder::with('user')->orderByDesc('created_at')->limit(50)->get();
        $pending = $orders->filter(fn ($o) => in_array($o->status, ['PENDING', 'AWAITING_CONFIRMATION']));
        return view('admin.crypto', compact('settings', 'orders', 'pending'));
    }

    public function updateCryptoSettings(Request $request)
    {
        $data = $request->validate([
            'buy_rate' => 'required|numeric|min:0.01',
            'sell_rate' => 'required|numeric|min:0.01',
            'network' => 'required|in:TRC20,ERC20,BEP20',
            'wallet_address' => 'nullable|string',
            'min_ghs' => 'required|numeric|min:1',
            'max_ghs' => 'required|numeric|gt:min_ghs',
            'is_enabled' => 'boolean',
            'buy_enabled' => 'boolean',
            'sell_enabled' => 'boolean',
        ]);

        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['buy_enabled'] = $request->boolean('buy_enabled');
        $data['sell_enabled'] = $request->boolean('sell_enabled');
        CryptoSettings::current()->update($data);
        return back()->with('success', 'USDT settings saved.');
    }

    public function confirmCrypto(string $id, WalletService $wallet)
    {
        $order = CryptoOrder::findOrFail($id);
        if ($order->isTerminal()) return back()->with('error', 'Order already finalized.');

        if ($order->type === 'SELL') {
            $wallet->credit($order->user_id, (float) $order->ghs_amount, 'CRYPTO_SELL_PAYOUT', null, "crypto:{$order->id}");
        }

        $order->update(['status' => 'COMPLETED']);
        return back()->with('success', 'Order confirmed.');
    }

    public function cancelCrypto(string $id, WalletService $wallet)
    {
        $order = CryptoOrder::findOrFail($id);
        if ($order->isTerminal()) return back()->with('error', 'Order already finalized.');

        if ($order->type === 'BUY') {
            $wallet->credit($order->user_id, (float) $order->ghs_amount, 'REFUND', null, "crypto:{$order->id}");
        }

        $order->update(['status' => 'CANCELLED']);
        return back()->with('success', 'Order cancelled.');
    }

    public function agents()
    {
        $agents        = User::where('role', 'AGENT')->orderByDesc('created_at')->get();
        $customers     = User::where('role', 'CUSTOMER')->orderBy('name')->get();
        $plan          = AgentPlan::current();
        $topupRequests = \App\Models\WalletTopupRequest::with('user')->where('status', 'PENDING')->orderByDesc('created_at')->get();
        return view('admin.agents', compact('agents', 'customers', 'plan', 'topupRequests'));
    }

    public function promoteAgent(Request $request)
    {
        $data = $request->validate(['user_id' => 'required|exists:users,id']);
        $user = User::findOrFail($data['user_id']);

        if ($user->role === 'ADMIN') {
            return back()->with('error', 'Cannot change an admin to agent.');
        }

        $user->role = 'AGENT';
        $user->agent_expires_at = now()->addDays(30);
        if (! $user->agent_code) {
            $user->agent_code = strtoupper(substr($user->phone, -4) . bin2hex(random_bytes(3)));
        }
        $user->save();
        return back()->with('success', "{$user->name} ({$user->phone}) is now an agent for 30 days.");
    }

    public function updateAgentPlan(Request $request)
    {
        $data = $request->validate([
            'monthly_fee' => 'required|numeric|min:0',
            'yearly_fee' => 'required|numeric|min:0',
            'is_enabled' => 'boolean',
        ]);
        $data['is_enabled'] = $request->boolean('is_enabled');
        AgentPlan::current()->update($data);
        return back()->with('success', 'Agent subscription fees updated.');
    }

    public function demoteAgent(string $id)
    {
        $user = User::findOrFail($id);
        if ($user->role !== 'AGENT') {
            return back()->with('error', 'This user is not an agent.');
        }

        $user->role = 'CUSTOMER';
        $user->save();
        return back()->with('success', "{$user->name} ({$user->phone}) demoted to customer.");
    }

    public function extendAgent(Request $request, string $id)
    {
        $data = $request->validate(['days' => 'required|integer|min:1|max:3650']);
        $agent = User::where('role', 'AGENT')->findOrFail($id);
        $startsFrom = $agent->agent_expires_at && $agent->agent_expires_at->isFuture()
            ? $agent->agent_expires_at->copy()
            : now();
        $agent->agent_expires_at = $startsFrom->addDays((int) $data['days']);
        $agent->save();

        \App\Models\Notification::send(
            $agent->id,
            'Agent subscription extended',
            "Your agent subscription is active until {$agent->agent_expires_at->format('M d, Y')}.",
            'success',
            '/agent'
        );

        return back()->with('success', "{$agent->name}'s subscription was extended by {$data['days']} days.");
    }

    /** Fund an agent's wallet directly from admin — no payment needed */
    public function fundAgent(Request $request, \App\Services\WalletService $wallet)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:100',
        ]);

        $agent = User::findOrFail($data['user_id']);
        $wallet->credit($agent->id, (float) $data['amount'], 'ADMIN_FUND', null, 'admin_fund_' . now()->timestamp);

        \App\Models\Notification::send(
            $agent->id,
            'Wallet funded',
            '₵' . number_format($data['amount'], 2) . ' has been added to your wallet by admin.' . ($data['note'] ? ' Note: ' . $data['note'] : ''),
            'success'
        );

        return back()->with('success', '₵' . number_format($data['amount'], 2) . ' added to ' . ($agent->name ?? $agent->phone) . "'s wallet.");
    }
    public function specialPricing()
    {
        $agents = User::where('role', 'AGENT')->orderBy('name')->get();
        $products = Product::where('is_available', true)->orderByRaw("FIELD(network, 'MTN', 'Telecel', 'AirtelTigo')")->orderBy('bundle_gb')->get();
        $selectedAgentId = request('agent_id');
        $specialPrices = [];

        if ($selectedAgentId) {
            $specialPrices = \App\Models\AgentSpecialPrice::where('user_id', $selectedAgentId)->pluck('special_price', 'product_id')->toArray();
        }

        return view('admin.special-pricing', compact('agents', 'products', 'selectedAgentId', 'specialPrices'));
    }

    /** Save special prices for a specific agent */
    public function saveSpecialPricing(Request $request)
    {
        $data = $request->validate([
            'agent_id' => 'required|exists:users,id',
            'prices' => 'required|array',
            'prices.*' => 'nullable|numeric|min:0',
        ]);

        $agentId = $data['agent_id'];

        foreach ($data['prices'] as $productId => $price) {
            if ($price === null || $price === '') {
                \App\Models\AgentSpecialPrice::where('user_id', $agentId)->where('product_id', $productId)->delete();
            } else {
                \App\Models\AgentSpecialPrice::updateOrCreate(
                    ['user_id' => $agentId, 'product_id' => $productId],
                    ['special_price' => $price],
                );
            }
        }

        return back()->with('success', 'Special prices saved for this agent.');
    }

    public function approveTopup(int $id, \App\Services\WalletService $wallet)
    {
        $req = \App\Models\WalletTopupRequest::findOrFail($id);
        if ($req->status !== 'PENDING') return back()->with('error', 'Already processed.');

        if ($req->user_id) {
            $wallet->credit($req->user_id, (float) $req->amount, 'TOPUP', null, 'manual_topup_' . $req->id);
            \App\Models\Notification::send($req->user_id, 'Wallet topped up ✓', '₵' . number_format($req->amount, 2) . ' has been added to your wallet.', 'success');
        }

        $req->update(['status' => 'APPROVED']);
        return back()->with('success', '₵' . number_format($req->amount, 2) . ' approved.');
    }

    public function rejectTopup(int $id)
    {
        $req = \App\Models\WalletTopupRequest::findOrFail($id);
        if ($req->status !== 'PENDING') return back()->with('error', 'Already processed.');

        $req->update(['status' => 'REJECTED']);

        if ($req->user_id) {
            \App\Models\Notification::send($req->user_id, 'Topup rejected', 'Your topup request of ₵' . number_format($req->amount, 2) . ' was rejected. Contact admin.', 'alert');
        }

        return back()->with('success', 'Topup request rejected.');
    }

    /** SMS devices page */
    public function smsDevices()
    {
        $devices = \App\Models\SmsDevice::orderByDesc('created_at')->get();
        return view('admin.sms-devices', compact('devices'));
    }

    /** Register a new SMS device and return the token */
    public function registerSmsDevice(Request $request)
    {
        $data = $request->validate([
            'device_name'       => 'required|string|max:100',
            'device_identifier' => 'required|string|max:100|unique:sms_devices',
        ]);

        $token  = \App\Models\SmsDevice::generateToken();
        \App\Models\SmsDevice::create([
            'device_name'       => $data['device_name'],
            'device_identifier' => $data['device_identifier'],
            'api_token_hash'    => $token['hash'],
            'is_active'         => true,
        ]);

        return redirect('/admin/sms-devices')->with('device_token', $token['plain']);
    }

    /** Toggle device active/inactive */
    public function toggleSmsDevice(int $id)
    {
        $device = \App\Models\SmsDevice::findOrFail($id);
        $device->update(['is_active' => ! $device->is_active]);
        return back()->with('success', 'Device ' . ($device->is_active ? 'activated' : 'deactivated') . '.');
    }
}
