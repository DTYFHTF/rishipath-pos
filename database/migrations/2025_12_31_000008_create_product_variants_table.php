<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 100)->unique();
            $table->decimal('pack_size', 10, 3);
            $table->string('unit', 20);
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('mrp_india', 10, 2)->nullable();
            $table->decimal('selling_price_nepal', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->string('barcode', 100)->unique()->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            
            $table->index('product_id');
            $table->index('sku');
            $table->index('barcode');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
