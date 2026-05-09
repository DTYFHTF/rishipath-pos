<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('agent_code', 32);
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('territory')->nullable();
            $table->decimal('commission_retail_pct', 5, 2)->default(0);
            $table->decimal('commission_wholesale_profit_pct', 5, 2)->default(30);
            $table->decimal('min_wholesale_amount', 12, 2)->default(10000);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'agent_code']);
            $table->unique(['organization_id', 'phone']);
            $table->index(['organization_id', 'name']);
            $table->index(['organization_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_agents');
    }
};
