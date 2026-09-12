<?php
namespace App\Http\Controllers;

use App\Models\AgentPlan;
use App\Models\AgentResellPrice;
use App\Models\Product;
use App\Services\WalletService;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /** Subscription page — shown to customers who want to become agents */
    public function subscribe()
    {
        $plan = AgentPlan::current();
        $user = auth()->user();
        return view('agent.subscribe', compact('plan', 'user'));
    }

    /** Process subscription payment from wallet */
    public function processSubscription(Request $request, WalletService $wallet)
    {
        $data = $request->validate(['period' => 'required|in:monthly,yearly']);
        $plan = AgentPlan::current();

        if (! $plan->is_enabled) {
            return back()->with('error', 'Agent subscriptions are currently disabled.');
        }

        $fee = $data['period'] === 'monthly' ? (float) $plan->monthly_fee : (float) $plan->yearly_fee;
        $days = $data['period'] === 'monthly' ? 30 : 365;
        $user = auth()->user();

        try {
            $wallet->debit($user->id, $fee, 'AGENT_SUB', null, "agent_sub_{$data['period']}");
        } catch (\Exception $e) {
            return back()->with('error', 'Insufficient wallet balance. You need GHS ' . number_format($fee, 2) . ' to subscribe.');
        }

        // Extend from current expiry if still active, otherwise from now
        $startsFrom = ($user->isActiveAgent()) ? $user->agent_expires_at : now();
        $user->role = 'AGENT';
        $user->agent_expires_at = $startsFrom->copy()->addDays($days);
        if (! $user->agent_code) {
            $user->agent_code = strtoupper(substr($user->phone, -4) . bin2hex(random_bytes(3)));
        }
        $user->save();

        return redirect('/agent')->with('success', ucfirst($data['period']) . ' agent subscription activated! You now get discounted pricing.');
    }

    /** Agent dashboard — their own pricing control panel */
    public function dashboard()
    {
        $user = auth()->user();
        if (! $user->isActiveAgent()) {
            return redirect('/agent/subscribe')->with('error', 'Your agent subscription has expired. Please renew to access the agent dashboard.');
        }

        // Generate code if this agent doesn't have one yet (e.g. was promoted before the feature existed)
        if (! $user->agent_code) {
            $user->agent_code = strtoupper(substr($user->phone, -4) . bin2hex(random_bytes(3)));
            $user->save();
        }

        $products = Product::where('is_available', true)->orderByRaw("FIELD(network, 'MTN', 'Telecel', 'AirtelTigo')")->orderBy('bundle_gb')->get();
        $resellPrices = AgentResellPrice::where('user_id', $user->id)->pluck('resell_price', 'product_id');
        $orders = $user->orders()->with('product')->orderByDesc('created_at')->limit(20)->get();

        return view('agent.dashboard', compact('user', 'products', 'resellPrices', 'orders'));
    }

    /** Save agent's own resell prices */
    public function updatePrices(Request $request)
    {
        $user = auth()->user();
        if (! $user->isActiveAgent()) {
            return back()->with('error', 'Agent subscription expired.');
        }

        $prices = $request->validate(['prices' => 'required|array', 'prices.*' => 'nullable|numeric|min:0']);

        foreach ($prices['prices'] as $productId => $resellPrice) {
            if ($resellPrice === null || $resellPrice === '') {
                AgentResellPrice::where('user_id', $user->id)->where('product_id', $productId)->delete();
                continue;
            }
            AgentResellPrice::updateOrCreate(
                ['user_id' => $user->id, 'product_id' => $productId],
                ['resell_price' => $resellPrice],
            );
        }

        return back()->with('success', 'Your resell prices have been saved.');
    }
}
