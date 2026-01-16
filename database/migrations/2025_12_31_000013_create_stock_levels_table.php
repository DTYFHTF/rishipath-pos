<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 10, 3)->default(0);
            $table->decimal('reserved_quantity', 10, 3)->default(0);
            $table->integer('reorder_level')->default(10);
            $table->timestamp('last_counted_at')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['product_variant_id', 'store_id']);
            $table->index('store_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
