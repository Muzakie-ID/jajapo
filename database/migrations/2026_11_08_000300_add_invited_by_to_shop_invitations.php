<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_invitations', function (Blueprint $table) {
            $table->foreignId('invited_by')->nullable()->after('role')->constrained('users');
            $table->index(['shop_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_invitations', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'email']);
            $table->dropConstrainedForeignId('invited_by');
        });
    }
};
