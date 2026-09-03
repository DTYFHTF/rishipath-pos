<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Null means no public price list link exists yet. Set once, from
            // the admin Price List page, then only ever regenerated (not
            // edited), so a leaked link is revoked by regenerating a new one.
            $table->string('price_list_public_token', 40)->nullable()->unique()->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('price_list_public_token');
        });
    }
};
