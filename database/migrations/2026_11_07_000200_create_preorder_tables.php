<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('story_image_path')->nullable();
            $table->string('category')->default('makanan');
            $table->json('labels')->nullable();
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('min_orders')->nullable();
            $table->boolean('delivery_enabled')->default(false);
            $table->unsignedInteger('delivery_fee')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['shop_id', 'slug']);
            $table->index(['status', 'closes_at']);
        });

        Schema::create('preorder_pickup_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preorder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pickup_point_id')->constrained();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preorder_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price');
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('sold_count')->default(0);
            $table->boolean('is_bundle')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->integer('price_delta')->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('qty')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('preorder_pickup_slots');
        Schema::dropIfExists('preorders');
    }
};
