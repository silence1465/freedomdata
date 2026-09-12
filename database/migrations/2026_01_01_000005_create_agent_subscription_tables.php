<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Single-row config for agent subscription fees
        Schema::create('agent_plans', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_fee', 10, 2)->default(50);
            $table->decimal('yearly_fee', 10, 2)->default(500);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('agent_plans')->insert([
            'id' => 1, 'monthly_fee' => 50, 'yearly_fee' => 500,
            'is_enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Track agent subscription on user
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('agent_expires_at')->nullable()->after('wallet_balance');
        });

        // Agent's own resell prices per product
        Schema::create('agent_resell_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('resell_price', 10, 2);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_resell_prices');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agent_expires_at');
        });
        Schema::dropIfExists('agent_plans');
    }
};
