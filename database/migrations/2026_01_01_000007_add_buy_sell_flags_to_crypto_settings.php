<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crypto_settings', function (Blueprint $table) {
            $table->boolean('buy_enabled')->default(true)->after('is_enabled');
            $table->boolean('sell_enabled')->default(true)->after('buy_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('crypto_settings', function (Blueprint $table) {
            $table->dropColumn(['buy_enabled', 'sell_enabled']);
        });
    }
};
