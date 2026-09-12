<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('sms_devices')) {
            Schema::create('sms_devices', function (Blueprint $table) {
                $table->id();
                $table->string('device_name', 100);
                $table->string('device_identifier', 100)->unique();
                $table->string('api_token_hash', 64)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('incoming_sms')) {
            Schema::create('incoming_sms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->nullable()->constrained('sms_devices')->nullOnDelete();
                $table->string('sender', 100);
                $table->text('message');
                $table->string('message_hash', 64)->unique();
                $table->timestamp('received_at');
                $table->boolean('processed')->default(false);
                $table->string('processing_status', 40)->default('pending')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sms_deposits')) {
            Schema::create('sms_deposits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('payment_reference', 30)->unique();
                $table->decimal('expected_amount', 12, 2);
                $table->decimal('received_amount', 12, 2)->nullable();
                $table->string('status', 40)->default('pending')->index();
                $table->string('payer_phone', 30)->nullable();
                $table->string('transaction_reference', 191)->nullable()->index();
                $table->string('purpose', 50)->default('wallet');
                $table->json('purpose_meta')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sms_payment_transactions')) {
            Schema::create('sms_payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deposit_id')->nullable()->constrained('sms_deposits')->nullOnDelete();
                $table->foreignId('incoming_sms_id')->nullable()->constrained('incoming_sms')->nullOnDelete();
                $table->string('transaction_id', 191)->nullable()->unique();
                $table->string('payer_phone', 30)->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->text('raw_message')->nullable();
                $table->string('match_method', 40)->nullable();
                $table->decimal('match_confidence', 5, 2)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 40)->default('matched')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_topup_requests')) {
            Schema::create('wallet_topup_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('transaction_id', 191)->unique();
                $table->string('mtn_reference', 100)->nullable();
                $table->string('sender_name', 100)->nullable();
                $table->string('status', 30)->default('PENDING')->index();
                $table->text('admin_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_topup_requests');
        Schema::dropIfExists('sms_payment_transactions');
        Schema::dropIfExists('sms_deposits');
        Schema::dropIfExists('incoming_sms');
        Schema::dropIfExists('sms_devices');
    }
};