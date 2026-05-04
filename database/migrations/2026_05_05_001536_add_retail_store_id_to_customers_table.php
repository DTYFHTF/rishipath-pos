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
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('retail_store_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('retail_stores')
                ->nullOnDelete();

            $table->index('retail_store_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['retail_store_id']);
            $table->dropColumn('retail_store_id');
        });
    }
};
