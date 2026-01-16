<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_received', 10, 3);
            $table->decimal('quantity_remaining', 10, 3);
            $table->decimal('quantity_sold', 10, 3)->default(0);
            $table->decimal('quantity_damaged', 10, 3)->default(0);
            $table->decimal('quantity_returned', 10, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'product_variant_id', 'batch_number']);
            $table->index('store_id');
            $table->index('product_variant_id');
            $table->index('expiry_date');
            $table->index('quantity_remaining');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
