<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('device_id')->unique()->nullable();
            $table->json('printer_config')->nullable();
            $table->json('scanner_config')->nullable();
            $table->string('last_receipt_number', 50)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
            $table->index('store_id');
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
