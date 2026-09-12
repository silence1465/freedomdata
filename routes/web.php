<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentShopController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CryptoController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\ManualPaymentController;
use App\Http\Controllers\ManualOrderController;
use App\Http\Controllers\SmsDepositController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/track', [TrackController::class, 'index'])->name('track');
Route::get('/track/{id}', [TrackController::class, 'show'])->name('track.show');
Route::get('/track/{id}/status', [TrackController::class, 'statusJson']);
Route::get('/crypto', [CryptoController::class, 'index'])->name('crypto');
Route::get('/payment/callback', [PaystackController::class, 'callback'])->name('payment.callback');
Route::get('/crypto/callback', [CryptoController::class, 'callback'])->name('crypto.callback');
Route::post('/payment/webhook', [PaystackController::class, 'webhook'])->name('payment.webhook');
Route::post('/buy/manual-init', [ManualPaymentController::class, 'initStorefront'])->middleware('auth')->name('buy.manual.init');
Route::post('/buy/guest-manual-init', [ManualPaymentController::class, 'initGuest'])->name('buy.guest.manual.init');
Route::get('/payment/{reference}', [ManualPaymentController::class, 'show'])->name('payment.show');
Route::get('/payment/status/{reference}', [ManualPaymentController::class, 'depositStatus'])->name('payment.status');
Route::post('/payment/report-reference', [ManualPaymentController::class, 'reportWrongReference'])->name('payment.report');
Route::post('/shop/{code}/manual-init', [ManualPaymentController::class, 'initAgentShop'])->name('shop.manual.init');
Route::post('/buy/manual', [ManualOrderController::class, 'store'])->name('buy.manual');

// SMS payment API — called by Android app (no CSRF, uses token auth)
// Public test endpoint — no auth required, just checks server is reachable
Route::post('/api/sms/test', function(\Illuminate\Http\Request $request) {
    return response()->json([
        'status' => 'ok',
        'received' => $request->all(),
        'headers' => [
            'X-Device-Token' => $request->header('X-Device-Token') ? 'present' : 'missing',
        ],
        'server_time' => now()->toIso8601String(),
    ]);
});

// Simplified receive endpoint — catches all errors and reports them
Route::post('/api/sms/receive-debug', function(\Illuminate\Http\Request $request) {
    try {
        $token = $request->header('X-Device-Token');
        $tokenHash = hash('sha256', $token ?? '');

        // Check if sms_devices table exists
        $tableExists = \Illuminate\Support\Facades\Schema::hasTable('sms_devices');
        if (!$tableExists) {
            return response()->json(['error' => 'sms_devices table missing — run SQL migration']);
        }

        // Check if device token matches
        $device = \App\Models\SmsDevice::where('api_token_hash', $tokenHash)->where('is_active', true)->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found — token mismatch', 'token_received' => $token ? substr($token, 0, 10).'...' : 'none']);
        }

        // Check incoming_sms table
        $smsTableExists = \Illuminate\Support\Facades\Schema::hasTable('incoming_sms');
        if (!$smsTableExists) {
            return response()->json(['error' => 'incoming_sms table missing — run SQL migration']);
        }

        return response()->json(['status' => 'ok', 'device' => $device->device_name, 'body' => $request->all()]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::prefix('api/sms')->group(function () {
    Route::post('/receive', function(\Illuminate\Http\Request $request) {
        try {
            $token = $request->header('X-Device-Token') ?? $request->input('device_token');
            if (str_starts_with((string)$token, 'Bearer ')) $token = substr($token, 7);

            $device = \App\Models\SmsDevice::where('api_token_hash', hash('sha256', $token ?? ''))
                ->where('is_active', true)->first();

            if (!$device) return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);

            $device->update(['last_seen_at' => now()]);

            $sender  = $request->input('from') ?? $request->input('sender') ?? '';
            $message = $request->input('text') ?? $request->input('message') ?? '';
            $ts      = $request->input('receivedStamp') ?? $request->input('sentStamp') ?? $request->input('received_at');
            if ($ts && $ts > 9999999999) $ts = intdiv((int)$ts, 1000);

            if (!$sender || !$message) return response()->json(['success' => false, 'error' => 'Missing sender or message'], 422);

            $hash = hash('sha256', $sender . $message);
            if (\App\Models\IncomingSms::where('message_hash', $hash)->exists()) {
                return response()->json(['success' => true, 'status' => 'duplicate']);
            }

            $sms = \App\Models\IncomingSms::create([
                'device_id'         => $device->id,
                'sender'            => $sender,
                'message'           => $message,
                'message_hash'      => $hash,
                'received_at'       => $ts ? now()->createFromTimestamp($ts) : now(),
                'processing_status' => 'pending',
            ]);

            $parser  = app(\App\Services\SmsParser::class);
            if (!$parser->isMoMoSender($sender)) {
                $sms->update(['processing_status' => 'rejected', 'processed' => true]);
                return response()->json(['success' => true, 'status' => 'ignored']);
            }

            $parsed  = $parser->parse($sender, $message);
            $matcher = app(\App\Services\PaymentMatcher::class);
            $result  = $matcher->match($sms, $parsed);

            return response()->json(['success' => true, 'matched' => $result['matched'], 'reason' => $result['reason'] ?? null]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SMS receive error: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    });

    Route::post('/heartbeat', function(\Illuminate\Http\Request $request) {
        try {
            $token = $request->header('X-Device-Token') ?? $request->input('device_token');
            if (str_starts_with((string)$token, 'Bearer ')) $token = substr($token, 7);
            $device = \App\Models\SmsDevice::where('api_token_hash', hash('sha256', $token ?? ''))
                ->where('is_active', true)->first();
            if (!$device) return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
            $device->update(['last_seen_at' => now()]);
            return response()->json(['success' => true, 'status' => 'ok']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    });

    Route::post('/register', function(\Illuminate\Http\Request $request) {
        if (!auth()->check() || !auth()->user()->isAdmin()) return response()->json(['error' => 'Admin only'], 403);
        $data = $request->validate(['device_name' => 'required|string|max:100', 'device_identifier' => 'required|string|max:100|unique:sms_devices']);
        $token = \App\Models\SmsDevice::generateToken();
        $device = \App\Models\SmsDevice::create(['device_name' => $data['device_name'], 'device_identifier' => $data['device_identifier'], 'api_token_hash' => $token['hash'], 'is_active' => true]);
        return response()->json(['device_id' => $device->id, 'api_token' => $token['plain'], 'warning' => 'Save this token — shown once only.']);
    });
});

// Agent public shop (no login required for customers)
Route::get('/shop/{code}', [AgentShopController::class, 'show'])->name('agent.shop');
Route::post('/shop/{code}/buy', [AgentShopController::class, 'buy']);
Route::get('/shop/{code}/callback', [AgentShopController::class, 'callback']);
Route::get('/shop/{code}/track', [AgentShopController::class, 'trackLookup']);
Route::get('/shop/{code}/track/{orderId}', [AgentShopController::class, 'track']);
Route::get('/shop/{code}/track/{orderId}/status', [AgentShopController::class, 'trackStatus']);

// Auth routes — rate limited to prevent brute force
Route::middleware('throttle:5,1')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated routes — rate limited to prevent spam
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::post('/buy', [StorefrontController::class, 'buy'])->name('buy');
    Route::post('/buy/paystack', [PaystackController::class, 'initializeDirectPay'])->name('buy.paystack');
    Route::post('/crypto/order', [CryptoController::class, 'store'])->name('crypto.order');
    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet');
    Route::get('/wallet/topup/create', [SmsDepositController::class, 'create'])->name('topup.create');
    Route::post('/wallet/topup/create', [SmsDepositController::class, 'store'])->name('topup.store');
    Route::get('/topup/{id}/status', [SmsDepositController::class, 'status'])->name('topup.status');
    Route::get('/topup/{id}/status-json', [SmsDepositController::class, 'statusJson']);

    Route::post('/wallet/topup', [WalletController::class, 'topup'])->name('wallet.topup');
    Route::post('/wallet/topup/paystack', [PaystackController::class, 'initializeTopup'])->name('wallet.topup.paystack');
    Route::post('/wallet/topup/manual', [WalletController::class, 'manualTopup'])->name('wallet.topup.manual');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    Route::get('/agent/subscribe', [AgentController::class, 'subscribe'])->name('agent.subscribe');
    Route::post('/agent/subscribe', [AgentController::class, 'processSubscription']);
    Route::get('/agent', [AgentController::class, 'dashboard'])->name('agent.dashboard');
    Route::post('/agent/prices', [AgentController::class, 'updatePrices'])->name('agent.prices');

    Route::get('/payout', [PayoutController::class, 'index'])->name('payout');
    Route::post('/payout', [PayoutController::class, 'store'])->name('payout.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/services', [AdminController::class, 'updateServices'])->name('services');
    Route::get('/pricing', [AdminController::class, 'pricing'])->name('pricing');
    Route::post('/pricing/{id}', [AdminController::class, 'updatePrice'])->name('pricing.update');
    Route::get('/crypto', [AdminController::class, 'cryptoDesk'])->name('crypto');
    Route::post('/crypto/settings', [AdminController::class, 'updateCryptoSettings'])->name('crypto.settings');
    Route::post('/crypto/{id}/confirm', [AdminController::class, 'confirmCrypto'])->name('crypto.confirm');
    Route::post('/crypto/{id}/cancel', [AdminController::class, 'cancelCrypto'])->name('crypto.cancel');
    Route::get('/agents', [AdminController::class, 'agents'])->name('agents');
    Route::post('/agents/fund', [AdminController::class, 'fundAgent'])->name('agents.fund');
    Route::post('/agents/promote', [AdminController::class, 'promoteAgent'])->name('agents.promote');
    Route::post('/topup-requests/{id}/approve', [AdminController::class, 'approveTopup'])->name('topup.approve');
    Route::post('/topup-requests/{id}/reject', [AdminController::class, 'rejectTopup'])->name('topup.reject');
    Route::post('/agents/plan', [AdminController::class, 'updateAgentPlan'])->name('agents.plan');
    Route::post('/agents/{id}/extend', [AdminController::class, 'extendAgent'])->name('agents.extend');
    Route::post('/agents/{id}/demote', [AdminController::class, 'demoteAgent'])->name('agents.demote');
    Route::get('/payouts', [PayoutController::class, 'adminIndex'])->name('payouts');
    Route::post('/payouts/{id}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{id}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');
    Route::get('/sms-devices', [AdminController::class, 'smsDevices'])->name('sms-devices');
    Route::post('/sms-devices', [AdminController::class, 'registerSmsDevice'])->name('sms-devices.register');
    Route::post('/sms-devices/{id}/toggle', [AdminController::class, 'toggleSmsDevice'])->name('sms-devices.toggle');
    Route::get('/special-pricing', [AdminController::class, 'specialPricing'])->name('special-pricing');
    Route::post('/special-pricing', [AdminController::class, 'saveSpecialPricing'])->name('special-pricing.save');
    Route::post('/manual-orders/{id}/approve', [ManualOrderController::class, 'approve'])->name('manual.approve');
    Route::post('/manual-orders/{id}/reject', [ManualOrderController::class, 'reject'])->name('manual.reject');
});

