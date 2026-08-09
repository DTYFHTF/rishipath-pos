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
        Schema::table('product_variants', function (Blueprint $table) {
            // A deliberate dealer price, same footing as manual_price_locked
            // on the retail side: null means "use the 13% cost formula",
            // set means a human decided this pack's wholesale price directly
            // and it is used as-is, formula bypassed entirely.
            $table->decimal('wholesale_price', 10, 2)->nullable()->after('cost_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('wholesale_price');
        });
    }
};
