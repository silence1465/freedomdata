<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataSikaClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey  = config('services.datasika.api_key', 'mock');
        $this->baseUrl = config('services.datasika.base_url', 'https://nrsfvhztpzwkadwciizp.supabase.co/functions/v1');
    }

    private function isMock(): bool
    {
        return empty($this->apiKey) || $this->apiKey === 'mock';
    }

    /**
     * Buy a data bundle.
     * MTN Flexa uses /api-buy-flexa, all others use /api-buy-data.
     */
    public function buyData(string $productId, string $recipient, string $idempotencyKey): array
    {
        if ($this->isMock()) {
            return [
                'order_id'        => 'MOCK-' . strtoupper(Str::random(8)),
                'status'          => 'Pending',
                'network'         => 'MTN',
                'bundle_gb'       => 1,
                'recipient'       => $recipient,
                'amount_charged'  => 5.00,
                'new_balance'     => 100.00,
            ];
        }

        // Determine which product this is to pick the correct endpoint
        $product = \App\Models\Product::where('data_sika_id', $productId)->first();
        $endpoint = ($product && strtolower($product->network) === 'mtn flexa')
            ? '/api-buy-flexa'
            : '/api-buy-data';

        return $this->request('post', $endpoint, [
            'product_id' => $productId,
            'recipient'  => $recipient,
        ], $idempotencyKey);
    }

    public function getOrderStatus(string $orderId): array
    {
        if ($this->isMock()) {
            return ['order_id' => $orderId, 'status' => 'delivered'];
        }

        try {
            $r = Http::withHeaders(['Authorization' => 'Bearer ' . $this->apiKey])
                ->timeout(20)
                ->get($this->baseUrl . '/api-order-status', ['order_id' => $orderId]);

            Log::info('DataSika order status raw response', ['order_id' => $orderId, 'response' => $r->json()]);

            if (! in_array($r->status(), [200, 202])) {
                throw new \Exception('datasika_error: ' . $r->body());
            }

            return $r->json() ?? [];
        } catch (\Throwable $e) {
            Log::error("DataSika getOrderStatus error: {$e->getMessage()}");
            throw $e;
        }
    }

    private function request(string $method, string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        $headers = ['Authorization' => 'Bearer ' . $this->apiKey];
        if ($idempotencyKey) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            $r = Http::withHeaders($headers)->timeout(20)->{$method}($this->baseUrl . $path, $data);
            if (! in_array($r->status(), [200, 202])) {
                Log::error("DataSika {$method} {$path} -> {$r->status()} ");
                throw new \Exception('datasika_error: ' . $r->body());
            }
            return $r->json() ?? [];
        } catch (\Throwable $e) {
            Log::error("DataSika error: {$e->getMessage()}");
            throw $e;
        }
    }
}