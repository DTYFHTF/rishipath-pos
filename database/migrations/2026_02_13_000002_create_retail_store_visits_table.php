<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retail_store_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retail_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visited_by')->constrained('users')->cascadeOnDelete();
            $table->date('visit_date');
            $table->time('visit_time')->nullable();
            $table->enum('visit_purpose', ['sales', 'delivery', 'collection', 'follow_up', 'new_contact', 'complaint', 'other'])->default('sales');
            $table->enum('visit_outcome', ['successful', 'partially_successful', 'unsuccessful', 'rescheduled', 'store_closed'])->nullable();

            // Quick feedback checklist
            $table->boolean('stock_available')->default(false);
            $table->boolean('good_display')->default(false);
            $table->boolean('clean_store')->default(false);
            $table->boolean('staff_trained')->default(false);
            $table->boolean('has_competition')->default(false);
            $table->boolean('order_placed')->default(false);
            $table->boolean('payment_collected')->default(false);
            $table->boolean('has_refrigeration')->default(false);

            // Ratings (1-5)
            $table->tinyInteger('store_condition_rating')->nullable();
            $table->tinyInteger('customer_footfall_rating')->nullable();
            $table->tinyInteger('cooperation_rating')->nullable();

            // Notes
            $table->text('issues_found')->nullable();
            $table->text('action_items')->nullable();
            $table->text('notes')->nullable();
            $table->text('competitor_notes')->nullable();

            // Follow-up
            $table->date('next_visit_date')->nullable();
            $table->decimal('order_value', 12, 2)->nullable();
            $table->json('photos')->nullable();

            $table->timestamps();

            $table->index('visit_date');
            $table->index(['retail_store_id', 'visit_date']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retail_store_visits');
    }
};
