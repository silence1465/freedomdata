<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type', 30)->default('info'); // info|success|warning|alert
            $table->string('link')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'is_read']);
        });

        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 30); // momo|bank
            $table->string('account_name');
            $table->string('account_number');
            $table->string('provider')->nullable(); // MTN MoMo, Telecel Cash, etc.
            $table->string('status', 20)->default('PENDING'); // PENDING|APPROVED|PAID|REJECTED
            $table->text('admin_notes')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
        Schema::dropIfExists('notifications');
    }
};
