<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-product markup over cost.
     *
     * Most products sit on the standard retail markup, but blends carry the
     * processing labour and premium lines are positioned higher. That was
     * previously inferred by matching product names in code, which silently
     * mis-prices anything renamed. Null means "use the standard markup".
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('retail_markup', 4, 2)
                ->nullable()
                ->after('unit_type')
                ->comment('Multiplier over cost, e.g. 1.30. Null uses PackPricing::RETAIL_MARKUP');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('retail_markup');
        });
    }
};
