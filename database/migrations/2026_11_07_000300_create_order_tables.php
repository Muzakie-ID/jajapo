<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('preorder_id')->constrained();
            $table->foreignId('buyer_id')->constrained('users');
            $table->string('fulfillment')->default('pickup');
            $table->foreignId('pickup_slot_id')->nullable()->constrained('preorder_pickup_slots');
            $table->string('address_note')->nullable();
            $table->string('note')->nullable();
            $table->string('buyer_phone', 20)->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->unsignedInteger('unique_code')->default(0);
            $table->unsignedInteger('total');
            $table->string('status')->default('pending_payment');
            $table->string('ticket_token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->foreignId('picked_up_by')->nullable()->constrained('users');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['buyer_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->string('name');
            $table->unsignedSmallInteger('qty');
            $table->unsignedInteger('unit_price');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->unsignedInteger('unique_code')->default(0);
            $table->string('gateway');
            $table->string('gateway_ref')->nullable()->unique();
            $table->text('qr_string')->nullable();
            $table->string('status')->default('pending');
            $table->string('method')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_event_id')->unique();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->unsignedInteger('amount');
            $table->foreignId('bank_account_id')->constrained();
            $table->string('status')->default('requested');
            $table->string('proof_path')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained();
            $table->foreignId('order_id')->nullable()->constrained();
            $table->foreignId('payout_id')->nullable()->constrained();
            $table->string('bucket');
            $table->string('type');
            $table->bigInteger('amount');
            $table->string('description');
            $table->timestamp('created_at');

            $table->index(['shop_id', 'bucket']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->string('reason');
            $table->unsignedInteger('amount');
            $table->string('refund_to')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
