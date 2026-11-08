<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Isi paket menunjuk varian tertentu, misalnya "Mac n Cheese, Original".
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('product_variants')->cascadeOnDelete();
        });

        // Salinan isi paket saat dipesan: untuk rekap produksi dan mengembalikan kuota.
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('components')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('components');
        });
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
