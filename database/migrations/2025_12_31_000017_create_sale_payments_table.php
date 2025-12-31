<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'upi', 'card', 'esewa', 'khalti', 'other']);
            $table->decimal('amount', 10, 2);
            $table->string('payment_gateway', 50)->nullable();
            $table->string('transaction_id')->nullable();
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->json('payment_response')->nullable();
            $table->timestamps();
            
            $table->index('sale_id');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
