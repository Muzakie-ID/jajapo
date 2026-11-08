<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekening baru menunggu super admin: is_active false, verified_at kosong, rejected_at kosong.
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('verified_at');
            $table->string('review_note')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn(['rejected_at', 'review_note']);
        });
    }
};
