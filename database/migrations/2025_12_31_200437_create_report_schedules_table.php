<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('report_type'); // sales, inventory, customer_analytics, cashier_performance
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'custom']);
            $table->string('cron_expression')->nullable(); // for custom frequency
            $table->json('parameters')->nullable(); // store_id, date_range, filters
            $table->json('recipients'); // email addresses
            $table->string('format')->default('pdf'); // pdf, excel, both
            $table->boolean('active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
