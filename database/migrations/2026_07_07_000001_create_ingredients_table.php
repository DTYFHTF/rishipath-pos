<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50); // e.g. SP-CUMIN — ID from the Ingredient Knowledge Base workbook
            $table->string('category', 50)->nullable(); // Spice / Seed / Dry Fruit & Nut / ...

            // Identity
            $table->string('name');
            $table->string('name_nepali')->nullable();
            $table->string('name_sanskrit')->nullable();
            $table->string('name_hindi')->nullable();
            $table->string('botanical_name')->nullable();
            $table->string('family')->nullable();
            $table->string('part_used')->nullable();
            $table->text('variants')->nullable();
            $table->boolean('is_hero')->default(false);

            // Ayurvedic properties
            $table->string('rasa')->nullable();
            $table->string('guna')->nullable();
            $table->string('virya')->nullable();
            $table->string('vipaka')->nullable();
            $table->string('dosha_effect')->nullable();
            $table->text('karma')->nullable();

            // Usage & safety
            $table->text('traditional_uses')->nullable();
            $table->text('modern_research')->nullable();
            $table->text('key_compounds')->nullable();
            $table->string('dosage')->nullable();
            $table->text('best_time')->nullable();
            $table->text('preparation_methods')->nullable();
            $table->text('good_for')->nullable();
            $table->text('contraindications')->nullable();

            // Combinations & products
            $table->text('combines_well_with')->nullable();
            $table->text('recipes_blends')->nullable();
            $table->text('incompatible_caution')->nullable();
            $table->text('substitutes')->nullable();
            $table->text('cross_references')->nullable();
            $table->text('household_uses')->nullable();
            $table->text('future_products')->nullable();

            // Storage & quality
            $table->string('shelf_life')->nullable();
            $table->string('storage')->nullable();
            $table->text('quality_indicators')->nullable();
            $table->unsignedTinyInteger('taste_sweet')->nullable();   // 0–5
            $table->unsignedTinyInteger('taste_bitter')->nullable();  // 0–5
            $table->unsignedTinyInteger('taste_pungent')->nullable(); // 0–5
            $table->unsignedTinyInteger('aroma')->nullable();         // 0–5

            // Search & pipeline
            $table->text('search_tags')->nullable();
            $table->text('capsule_potential')->nullable();
            $table->string('capsule_priority', 50)->nullable();

            // Optional link to the POS product that carries this ingredient's pricing
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
