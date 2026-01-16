<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['discount_percentage', 'discount_fixed', 'free_product', 'cashback']);
            $table->integer('points_required');
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete(); // For free_product type
            $table->integer('quantity')->default(1); // For free_product
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('max_redemptions_per_customer')->nullable();
            $table->integer('total_redemptions')->default(0);
            $table->json('tier_restrictions')->nullable(); // Which tiers can redeem
            $table->string('image_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('type');
            $table->index('points_required');
            $table->index('active');
            $table->index(['valid_from', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewards');
    }
};
