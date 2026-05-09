<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('sales_agent_id')
                ->nullable()
                ->after('cashier_id')
                ->constrained('sales_agents')
                ->nullOnDelete();

            $table->enum('order_channel', ['retail', 'wholesale'])
                ->default('retail')
                ->after('payment_status');

            $table->decimal('wholesale_base_amount', 12, 2)
                ->nullable()
                ->after('total_amount');

            $table->decimal('company_profit_amount', 12, 2)
                ->default(0)
                ->after('wholesale_base_amount');

            $table->decimal('agent_commission_amount', 12, 2)
                ->default(0)
                ->after('company_profit_amount');

            $table->index(['organization_id', 'sales_agent_id']);
            $table->index(['organization_id', 'order_channel']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'sales_agent_id']);
            $table->dropIndex(['organization_id', 'order_channel']);
            $table->dropConstrainedForeignId('sales_agent_id');
            $table->dropColumn([
                'order_channel',
                'wholesale_base_amount',
                'company_profit_amount',
                'agent_commission_amount',
            ]);
        });
    }
};
