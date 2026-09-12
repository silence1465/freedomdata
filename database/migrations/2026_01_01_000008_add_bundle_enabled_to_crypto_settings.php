<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crypto_settings', function (Blueprint $table) {
            $table->boolean('bundle_enabled')->default(true)->after('sell_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('crypto_settings', function (Blueprint $table) {
            $table->dropColumn('bundle_enabled');
        });
    }
};
