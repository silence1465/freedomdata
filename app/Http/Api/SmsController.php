<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomingSms;
use App\Models\SmsDevice;
use App\Services\PaymentMatcher;
use App\Services\SmsParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    /**
     * Receive incoming SMS from Android device.
     * Supports two formats:
     *
     * 1. bogkonstantin app (https://github.com/bogkonstantin/android_income_sms_gateway_webhook):
     *    { "from": "Mobile Money", "text": "...", "sentStamp": 1234567890000, "receivedStamp": 1234567890000 }
     *
     * 2. Our custom app format:
     *    { "sender": "Mobile Money", "message": "...", "received_at": 1234567890 }
     */
    public function receive(Request $request, SmsParser $parser, PaymentMatcher $matcher)
    {
        // Authenticate device
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['error' => 'Unauthorized device'], 401);
        }

        $device->update(['last_seen_at' => now()]);

        // Normalise both payload formats into sender + message + timestamp
        $body = $request->all();

        // bogkonstantin format uses "from" and "text"
        $sender     = $body['from']     ?? $body['sender']  ?? '';
        $message    = $body['text']     ?? $body['message'] ?? '';
        $receivedAt = $body['receivedStamp'] ?? $body['sentStamp'] ?? $body['received_at'] ?? null;

        // Convert milliseconds to seconds if needed (bogkonstantin sends ms)
        if ($receivedAt && $receivedAt > 9_999_999_999) {
            $receivedAt = intdiv((int) $receivedAt, 1000);
        }

        if (! $sender || ! $message) {
            return response()->json(['error' => 'Missing sender or message'], 422);
        }

        // Deduplication — same message received twice
        $messageHash = hash('sha256', $sender . $message);
        if (IncomingSms::where('message_hash', $messageHash)->exists()) {
            return response()->json(['success' => true, 'status' => 'duplicate']);
        }

        // Save raw SMS
        $sms = IncomingSms::create([
            'device_id'          => $device->id,
            'sender'             => $sender,
            'message'            => $message,
            'message_hash'       => $messageHash,
            'received_at'        => $receivedAt ? now()->createFromTimestamp($receivedAt) : now(),
            'processing_status'  => 'pending',
        ]);

        // Only process MoMo SMS
        if (! $parser->isMoMoSender($sender)) {
            $sms->update(['processing_status' => 'rejected', 'processed' => true]);
            return response()->json(['success' => true, 'status' => 'ignored', 'reason' => 'not_momo_sender']);
        }

        // Parse and match
        $parsed = $parser->parse($sender, $message);
        Log::info('SMS received and parsed', ['sender' => $sender, 'parsed' => $parsed]);

        $matchResult = $matcher->match($sms, $parsed);

        return response()->json([
            'success'    => true,
            'status'     => 'ok',
            'sms_id'     => $sms->id,
            'is_payment' => $parsed['is_payment_sms'],
            'matched'    => $matchResult['matched'],
            'reason'     => $matchResult['reason'] ?? null,
        ]);
    }

    /** Heartbeat — device pings to show it's online */
    public function heartbeat(Request $request)
    {
        $device = $this->authenticateDevice($request);
        if (! $device) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        $device->update(['last_seen_at' => now()]);
        return response()->json(['status' => 'ok', 'server_time' => now()->toIso8601String()]);
    }

    /** Register a new device — admin only */
    public function register(Request $request)
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            return response()->json(['error' => 'Admin only'], 403);
        }

        $data = $request->validate([
            'device_name'       => 'required|string|max:100',
            'device_identifier' => 'required|string|max:100|unique:sms_devices',
        ]);

        $token = SmsDevice::generateToken();

        $device = SmsDevice::create([
            'device_name'       => $data['device_name'],
            'device_identifier' => $data['device_identifier'],
            'api_token_hash'    => $token['hash'],
            'is_active'         => true,
        ]);

        return response()->json([
            'device_id'   => $device->id,
            'device_name' => $device->device_name,
            'api_token'   => $token['plain'],
            'warning'     => 'Save this token now — it cannot be retrieved again.',
        ]);
    }

    private function authenticateDevice(Request $request): ?SmsDevice
    {
        // Check header first, then URL param (bogkonstantin app supports custom headers)
        $token = $request->header('X-Device-Token')
            ?? $request->header('Authorization')
            ?? $request->query('token')
            ?? $request->input('device_token');

        // Strip "Bearer " prefix if present
        if ($token && str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        if (! $token) return null;

        return SmsDevice::where('api_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->first();
    }
}