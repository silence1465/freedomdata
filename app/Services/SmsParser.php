<?php
namespace App\Services;

class SmsParser
{
    private string $referencePrefix = 'FD-';

    /**
     * Parse an incoming SMS and extract payment details.
     * Based on real MTN Ghana MoMo SMS format:
     * "Payment received for GHS 303.00 from VIDA ANAMBER
     *  Current Balance: GHS 2451.24.
     *  Reference: 3435.
     *  Transaction ID: 87425747421.
     *  TRANSACTION FEE: 0.00"
     */
    public function parse(string $sender, string $message): array
    {
        $result = [
            'is_payment_sms' => false,
            'amount' => null,
            'sender_phone' => null,
            'sender_name' => null,
            'transaction_id' => null,
            'mtn_reference' => null,
            'payment_reference' => null,
            'confidence' => 0.0,
        ];

        // Extract our FD- reference code if customer included it
        if (preg_match('/\b(FD-[A-Z0-9]{6})\b/i', $message, $m)) {
            $result['payment_reference'] = strtoupper($m[1]);
            $result['confidence'] += 0.4;
        }

        // Extract amount — MTN Ghana real format: "Payment received for GHS 303.00"
        $amount = $this->extractAmount($message);
        if ($amount) {
            $result['amount'] = $amount;
            $result['is_payment_sms'] = true;
            $result['confidence'] += 0.3;
        }

        // Extract Transaction ID — "Transaction ID: 87425747421."
        if (preg_match('/Transaction\s*ID[:\s]+(\d{6,20})/i', $message, $m)) {
            $result['transaction_id'] = $m[1];
            $result['confidence'] += 0.2;
        }

        // Extract MTN Reference — "Reference: 3435."
        if (preg_match('/\bReference[:\s]+(\w+)/i', $message, $m)) {
            $result['mtn_reference'] = $m[1];
            $result['confidence'] += 0.1;
        }

        // Extract sender name — "from VIDA ANAMBER"
        if (preg_match('/from\s+([A-Z][A-Z\s]+?)(?:\n|Current|Available|\.)/i', $message, $m)) {
            $result['sender_name'] = trim($m[1]);
        }

        // Extract phone number if present
        if (preg_match('/\b(0[235]\d{8})\b/', $message, $m)) {
            $result['sender_phone'] = $m[1];
        }

        $result['confidence'] = min(1.0, round($result['confidence'], 2));

        return $result;
    }

    private function extractAmount(string $message): ?float
    {
        $patterns = [
            '/Payment received for GHS\s*([\d,]+\.?\d*)/i',
            '/received\s+GHS\s*([\d,]+\.?\d*)/i',
            '/Cash\s*In[:\s]+GHS\s*([\d,]+\.?\d*)/i',
            '/GHS\s*([\d,]+\.?\d*)\s+from/i',
            '/([\d,]+\.?\d*)\s+GHS/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $m)) {
                $amount = (float) str_replace(',', '', $m[1]);
                if ($amount > 0) return $amount;
            }
        }

        return null;
    }

    public function isMoMoSender(string $sender): bool
    {
        $knownSenders = ['Mobile Money','MOMO'];
        foreach ($knownSenders as $known) {
            if (stripos($sender, $known) !== false) return true;
        }
        return false;
    }
}