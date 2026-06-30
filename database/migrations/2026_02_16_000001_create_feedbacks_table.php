<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Polymorphic relation to any feedbackable model (RetailStore, BulkOrderInquiry, etc.)
            $table->morphs('feedbackable');

            // Who created this feedback/note
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // For replies - null means top-level feedback
            $table->foreignId('parent_id')->nullable()->constrained('feedbacks')->cascadeOnDelete();

            // Feedback content
            $table->enum('type', ['note', 'feedback', 'complaint', 'suggestion', 'inquiry'])->default('note');
            $table->string('subject')->nullable();
            $table->text('message');

            // Status tracking
            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Admin response
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            // Attachments
            $table->json('attachments')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
