<?php

namespace Tests\Feature;

use App\Models\AgentResellPrice;
use App\Models\CryptoSettings;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;
        return User::create(array_merge([
            'name' => 'Dummy User ' . $sequence,
            'phone' => '054000' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'email' => "dummy{$sequence}@example.test",
            'password' => Hash::make('password'),
        ], $attributes));
    }

    public function test_public_customer_agent_and_admin_pages_render(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $customer = $this->user(['role' => 'CUSTOMER']);
        $agent = $this->user(['role' => 'AGENT', 'agent_code' => 'DUMMYSHOP', 'agent_expires_at' => now()->addMonth()]);
        $product = Product::firstOrFail();
        AgentResellPrice::create(['user_id' => $agent->id, 'product_id' => $product->id, 'resell_price' => 10]);

        $this->get('/')->assertOk();
        $this->get('/track')->assertOk();
        $this->get('/shop/DUMMYSHOP')->assertOk();
        $this->actingAs($customer)->get('/wallet')->assertOk();
        $this->actingAs($agent)->get('/agent')->assertOk();
        foreach (['/admin', '/admin/pricing', '/admin/crypto', '/admin/agents', '/admin/payouts', '/admin/sms-devices', '/admin/special-pricing'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_can_extend_agent_subscription_and_agent_is_notified(): void
    {
        $admin = $this->user(['role' => 'ADMIN']);
        $agent = $this->user(['role' => 'AGENT', 'agent_expires_at' => now()->subDay()]);
        $this->actingAs($admin)->post("/admin/agents/{$agent->id}/extend", ['days' => 30])->assertRedirect();
        $this->assertTrue($agent->fresh()->agent_expires_at->isFuture());
        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'title' => 'Agent subscription extended']);
    }

    public function test_agent_can_hold_approve_or_reject_only_own_shop_orders(): void
    {
        $agent = $this->user(['role' => 'AGENT', 'agent_expires_at' => now()->addMonth()]);
        $otherAgent = $this->user(['role' => 'AGENT', 'agent_expires_at' => now()->addMonth()]);
        $product = Product::firstOrFail();
        $makeOrder = fn (User $owner, string $reference) => Order::create([
            'id' => (string) Str::uuid(), 'idempotency_key' => 'manual_' . $reference,
            'user_id' => $owner->id, 'product_id' => $product->id, 'recipient' => '0541234567',
            'amount_charged' => 10, 'cost_amount' => 7, 'payment_reference' => $reference,
            'payment_method' => 'manual', 'status' => 'AWAITING_APPROVAL',
        ]);

        $held = $makeOrder($agent, 'TXN-HOLD');
        $this->actingAs($agent)->post("/agent/orders/{$held->id}/hold")->assertRedirect();
        $this->assertSame('ON_HOLD', $held->fresh()->status);

        $this->actingAs($agent)->post("/agent/orders/{$held->id}/approve")->assertRedirect();
        $this->assertSame('PENDING', $held->fresh()->status);

        $rejected = $makeOrder($agent, 'TXN-REJECT');
        $this->actingAs($agent)->post("/agent/orders/{$rejected->id}/reject")->assertRedirect();
        $this->assertSame('FAILED', $rejected->fresh()->status);

        $foreign = $makeOrder($otherAgent, 'TXN-FOREIGN');
        $this->actingAs($agent)->post("/agent/orders/{$foreign->id}/approve")->assertForbidden();
    }

    public function test_manual_payment_fields_persist_and_reference_is_unique(): void
    {
        $user = $this->user();
        $product = Product::firstOrFail();
        Order::create([
            'id' => (string) Str::uuid(), 'idempotency_key' => 'manual_UNIQUE-TXN',
            'user_id' => $user->id, 'product_id' => $product->id, 'recipient' => '0541234567',
            'amount_charged' => 10, 'cost_amount' => 7, 'payment_reference' => 'UNIQUE-TXN',
            'payment_method' => 'manual', 'payment_screenshot' => 'payment-screenshots/proof.jpg',
            'status' => 'AWAITING_APPROVAL',
        ]);
        $this->assertDatabaseHas('orders', ['payment_reference' => 'UNIQUE-TXN', 'payment_method' => 'manual', 'payment_screenshot' => 'payment-screenshots/proof.jpg']);
    }
}
