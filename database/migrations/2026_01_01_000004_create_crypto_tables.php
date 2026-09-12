<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crypto_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('buy_rate', 10, 2)->default(0);
            $table->decimal('sell_rate', 10, 2)->default(0);
            $table->string('network', 10)->default('TRC20');
            $table->string('wallet_address')->nullable();
            $table->decimal('min_ghs', 10, 2)->default(20);
            $table->decimal('max_ghs', 10, 2)->default(5000);
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();
        });

        DB::table('crypto_settings')->insert([
            'id' => 1, 'buy_rate' => 17.50, 'sell_rate' => 16.80,
            'network' => 'TRC20', 'wallet_address' => 'TXxxxxxxxxPLACEHOLDER',
            'min_ghs' => 20, 'max_ghs' => 5000, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('crypto_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // BUY | SELL
            $table->decimal('ghs_amount', 12, 2);
            $table->decimal('usdt_amount', 12, 4);
            $table->decimal('rate_used', 10, 2);
            $table->string('network', 10);
            $table->string('customer_wallet_address')->nullable();
            $table->string('tx_hash')->nullable();
            $table->string('status', 30)->default('PENDING');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_orders');
        Schema::dropIfExists('crypto_settings');
    }
};
