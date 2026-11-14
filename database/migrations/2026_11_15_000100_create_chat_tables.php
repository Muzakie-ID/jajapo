<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu percakapan untuk setiap pasangan pembeli dan toko.
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            // Penanda baca berupa id pesan terakhir yang sudah dilihat masing-masing pihak.
            $table->unsignedBigInteger('buyer_read_id')->default(0);
            $table->unsignedBigInteger('shop_read_id')->default(0);
            $table->timestamps();
            $table->unique(['shop_id', 'buyer_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users');
            $table->string('sender_role', 10); // buyer atau shop
            $table->text('body')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
