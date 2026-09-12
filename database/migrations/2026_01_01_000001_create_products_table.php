<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('data_sika_id')->unique();
            $table->string('network', 30);
            $table->decimal('bundle_gb', 6, 2);
            $table->decimal('cost_price', 10, 2);
            $table->decimal('sell_price', 10, 2);
            $table->decimal('agent_price', 10, 2)->nullable();
            $table->string('service_type', 30)->default('DATA_BUNDLE');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->index(['network', 'service_type']);
        });

        // Seed placeholder products so the storefront works without DataSika
        $bundles = [
            ['MTN', 1, 5.00, 6.50],
            ['MTN', 2, 9.00, 11.50],
            ['MTN', 5, 20.00, 25.00],
            ['MTN', 10, 38.00, 46.00],
            ['MTN', 20, 70.00, 85.00],
            ['Telecel', 1, 4.50, 6.00],
            ['Telecel', 3, 12.00, 15.00],
            ['Telecel', 7, 27.00, 33.00],
            ['Telecel', 15, 52.00, 62.00],
            ['AirtelTigo', 1, 4.00, 5.50],
            ['AirtelTigo', 3, 11.00, 14.00],
            ['AirtelTigo', 8, 28.00, 34.00],
            ['AirtelTigo', 20, 65.00, 78.00],
        ];

        foreach ($bundles as [$net, $gb, $cost, $sell]) {
            DB::table('products')->insert([
                'id' => (string) Str::uuid(),
                'data_sika_id' => 'placeholder-' . strtolower($net) . '-' . $gb . 'gb',
                'network' => $net,
                'bundle_gb' => $gb,
                'cost_price' => $cost,
                'sell_price' => $sell,
                'service_type' => 'DATA_BUNDLE',
                'is_available' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
