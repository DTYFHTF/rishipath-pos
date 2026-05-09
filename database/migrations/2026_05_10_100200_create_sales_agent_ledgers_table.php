<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_agent_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_agent_id')->constrained('sales_agents')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->enum('entry_type', ['credit', 'debit', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'sales_agent_id']);
            $table->index(['sales_agent_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agent_ledgers');
    }
};
