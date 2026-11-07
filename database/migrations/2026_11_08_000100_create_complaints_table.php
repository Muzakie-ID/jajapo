<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('buyer_id')->constrained('users');
            $table->string('reason', 40);
            $table->text('description');
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('amount_requested');
            $table->string('refund_to', 160);
            // open: menunggu penjual, disputed: penjual menolak dan super admin memutuskan,
            // refunded: refund disetujui, rejected: komplain ditolak.
            $table->string('status', 20)->default('open');
            // Nominal yang dipindah dari saldo tersedia kembali ke tertahan selama komplain berjalan.
            $table->unsignedInteger('hold_amount')->default(0);
            $table->text('seller_response')->nullable();
            $table->timestamp('seller_responded_at')->nullable();
            $table->unsignedInteger('amount_approved')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index('status');
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('complaint_id')->nullable()->after('order_id')->constrained();
            $table->timestamp('transferred_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complaint_id');
            $table->dropColumn('transferred_at');
        });
        Schema::dropIfExists('complaints');
    }
};
