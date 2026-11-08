<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Halaman bayar dari gateway, cadangan kalau QR di halaman Jajapo tidak bisa dipindai.
        Schema::table('payments', function (Blueprint $table) {
            $table->string('checkout_url', 500)->nullable()->after('qr_string');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
    }
};
