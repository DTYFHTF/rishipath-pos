<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('sales_agent_id')
                ->nullable()
                ->after('retail_store_id')
                ->constrained('sales_agents')
                ->nullOnDelete();

            $table->index(['organization_id', 'sales_agent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'sales_agent_id']);
            $table->dropConstrainedForeignId('sales_agent_id');
        });
    }
};
