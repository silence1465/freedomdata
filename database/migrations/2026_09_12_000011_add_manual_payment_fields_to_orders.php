<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_reference')) $table->string('payment_reference', 100)->nullable()->index();
            if (! Schema::hasColumn('orders', 'payment_method')) $table->string('payment_method', 30)->nullable();
            if (! Schema::hasColumn('orders', 'payment_screenshot')) $table->string('payment_screenshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_values(array_filter(['payment_reference', 'payment_method', 'payment_screenshot'], fn ($column) => Schema::hasColumn('orders', $column)));
            if ($columns) $table->dropColumn($columns);
        });
    }
};
