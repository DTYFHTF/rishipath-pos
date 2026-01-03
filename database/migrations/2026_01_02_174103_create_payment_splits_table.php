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
        Schema::create('payment_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            
            // Payment method details
            $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit', 'wallet'])->default('cash');
            $table->decimal('amount', 15, 2);
            
            // Additional payment info
            $table->string('reference_number')->nullable(); // Card transaction ID, cheque number, etc.
            $table->string('card_last4')->nullable(); // Last 4 digits of card
            $table->string('card_type')->nullable(); // Visa, Mastercard, etc.
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('sale_id');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_splits');
    }
};
