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
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            // Transaction details
            $table->enum('entry_type', ['sale', 'payment', 'credit_note', 'opening_balance', 'adjustment'])->default('sale');
            $table->string('reference_type')->nullable(); // Sale, Payment, etc.
            $table->unsignedBigInteger('reference_id')->nullable(); // ID of related record
            $table->string('reference_number')->nullable(); // Invoice/Receipt number

            // Financial details
            $table->decimal('debit_amount', 15, 2)->default(0); // What customer owes
            $table->decimal('credit_amount', 15, 2)->default(0); // What customer paid
            $table->decimal('balance', 15, 2)->default(0); // Running balance

            // Additional info
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->date('due_date')->nullable();

            // Payment details (if entry_type is payment)
            $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'])->nullable();
            $table->string('payment_reference')->nullable(); // Cheque number, transaction ID, etc.

            // Status tracking
            $table->enum('status', ['pending', 'completed', 'cancelled', 'overdue'])->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['customer_id', 'transaction_date']);
            $table->index(['organization_id', 'store_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
