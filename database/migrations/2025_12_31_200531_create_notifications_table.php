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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // low_stock, sales_alert, report_failed, etc.
            $table->string('title');
            $table->text('message');
            $table->enum('severity', ['info', 'warning', 'error', 'critical']);
            $table->json('data')->nullable(); // additional context
            $table->json('recipients'); // email addresses or user IDs
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->text('send_error')->nullable();
            $table->foreignId('related_id')->nullable(); // related record (product, sale, etc.)
            $table->string('related_type')->nullable(); // model type
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'sent', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
