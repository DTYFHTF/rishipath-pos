<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50); // Bronze, Silver, Gold, Platinum
            $table->string('slug', 50)->unique();
            $table->integer('min_points')->default(0);
            $table->integer('max_points')->nullable();
            $table->decimal('points_multiplier', 3, 2)->default(1.00); // e.g., 1.5x for Gold
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->json('benefits')->nullable(); // Special perks
            $table->string('badge_color', 20)->default('gray');
            $table->string('badge_icon', 50)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('organization_id');
            $table->index('slug');
            $table->index(['min_points', 'max_points']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_tiers');
    }
};
