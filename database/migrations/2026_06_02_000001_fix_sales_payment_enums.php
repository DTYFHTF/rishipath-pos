<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash','upi','card','esewa','khalti','other','credit') NOT NULL");
        DB::statement("ALTER TABLE sales MODIFY payment_status ENUM('paid','pending','partial','refunded','unpaid') NOT NULL DEFAULT 'paid'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE sales MODIFY payment_method ENUM('cash','upi','card','esewa','khalti','other') NOT NULL");
        DB::statement("ALTER TABLE sales MODIFY payment_status ENUM('paid','pending','partial','refunded') NOT NULL DEFAULT 'paid'");
    }
};