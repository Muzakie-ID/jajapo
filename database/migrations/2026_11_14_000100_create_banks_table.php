<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pilihan awal. Super admin bisa menambah, mengganti, atau menyembunyikannya.
        $now = now();
        DB::table('banks')->insert(collect([
            'BCA', 'BNI', 'BRI', 'Mandiri', 'BSI', 'BTN', 'CIMB Niaga', 'Permata', 'Danamon',
            'BPD DIY', 'Bank Jago', 'SeaBank', 'GoPay', 'DANA', 'OVO', 'ShopeePay',
        ])->map(fn ($name) => ['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
