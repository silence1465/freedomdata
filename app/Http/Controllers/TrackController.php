<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class TrackController extends Controller
{
    public function index(Request $request)
    {
        $phone = $request->query('phone');
        $orders = null;

        if ($phone) {
            $orders = Order::with('product')
                ->where('recipient', $phone)
                ->orderByDesc('created_at')
                ->paginate(20)
                ->appends(['phone' => $phone]);
        }

        return view('track', compact('phone', 'orders'));
    }

    public function show(string $id)
    {
        $order = Order::with('product')->findOrFail($id);
        return view('track-detail', compact('order'));
    }

    public function statusJson(string $id)
    {
        $order = Order::findOrFail($id);
        return response()->json([
            'status' => $order->status,
            'updated_at' => $order->updated_at->diffForHumans(),
        ]);
    }
}
