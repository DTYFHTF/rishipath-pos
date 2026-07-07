<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_compositions', function (Blueprint $table) {
            $table->id();
            // The blend/finished product (e.g. Garam Masala)
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // The raw-material product used for costing, if it exists in the catalog
            $table->foreignId('component_product_id')->nullable()->constrained('products')->nullOnDelete();
            // Link to the ingredient knowledge base entry, if any
            $table->foreignId('ingredient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // display name, e.g. "Lavanga (Clove)"
            $table->decimal('quantity', 10, 2); // parts by weight (grams per batch)
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_compositions');
    }
};
