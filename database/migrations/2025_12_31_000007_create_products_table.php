<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->string('name_nepali')->nullable();
            $table->string('name_hindi')->nullable();
            $table->string('name_sanskrit')->nullable();
            $table->text('description')->nullable();
            $table->string('product_type', 50);
            $table->enum('unit_type', ['weight', 'volume', 'piece']);
            $table->boolean('has_variants')->default(false);
            $table->enum('tax_category', ['essential', 'standard', 'luxury'])->default('standard');
            $table->boolean('requires_batch')->default(true);
            $table->boolean('requires_expiry')->default(true);
            $table->integer('shelf_life_months')->nullable();
            $table->boolean('is_prescription_required')->default(false);
            $table->json('ingredients')->nullable();
            $table->text('usage_instructions')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            
            $table->index('organization_id');
            $table->index('category_id');
            $table->index('sku');
            $table->index('product_type');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
