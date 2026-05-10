<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('settlement_reference', 255)
                ->nullable()
                ->after('payment_reference')
                ->comment('QR code, bank txn ID, or other proof of payment settlement');

            $table->timestamp('settlement_confirmed_at')
                ->nullable()
                ->after('settlement_reference')
                ->comment('When admin confirmed settlement is correct');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['settlement_reference', 'settlement_confirmed_at']);
        });
    }
};
