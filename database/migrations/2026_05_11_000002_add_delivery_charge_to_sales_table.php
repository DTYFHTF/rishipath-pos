<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('delivery_charge', 10, 2)
                ->default(0)
                ->after('agent_commission_amount');

            $table->boolean('delivery_charge_applied')
                ->default(false)
                ->after('delivery_charge')
                ->comment('True if delivery charge was auto-applied by system rule');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['delivery_charge', 'delivery_charge_applied']);
        });
    }
};
