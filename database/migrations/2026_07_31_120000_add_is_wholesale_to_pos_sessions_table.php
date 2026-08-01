<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parked carts must remember whether they are being billed at dealer
     * rates — otherwise reopening a wholesale cart silently re-prices it at
     * MRP and the shop is overcharged.
     */
    public function up(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->boolean('is_wholesale')->default(false)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('pos_sessions', function (Blueprint $table) {
            $table->dropColumn('is_wholesale');
        });
    }
};
