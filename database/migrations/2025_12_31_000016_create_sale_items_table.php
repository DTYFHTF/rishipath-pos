<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            $table->string('product_name');
            $table->string('product_sku', 100);
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 20);
            $table->decimal('price_per_unit', 10, 2);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2);
            $table->decimal('tax_amount', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();
            
            $table->index('sale_id');
            $table->index('product_variant_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
