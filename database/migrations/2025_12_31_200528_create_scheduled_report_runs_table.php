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
        Schema::create('scheduled_report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_schedule_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('file_path')->nullable(); // path to generated report
            $table->integer('file_size')->nullable(); // in bytes
            $table->integer('records_processed')->nullable();
            $table->json('metadata')->nullable(); // additional info
            $table->timestamps();

            $table->index(['report_schedule_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_runs');
    }
};
