<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->string('data_sika_order_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained();
            $table->string('recipient', 15);
            $table->decimal('amount_charged', 10, 2);
            $table->decimal('cost_amount', 10, 2);
            $table->string('status', 30)->default('PENDING');
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('poll_attempts')->default(0);
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
