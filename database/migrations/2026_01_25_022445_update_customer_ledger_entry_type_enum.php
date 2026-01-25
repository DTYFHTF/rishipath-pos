<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table with new enum values
        if (DB::getDriverName() === 'sqlite') {
            // Create temporary table with new structure
            Schema::create('customer_ledger_entries_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->enum('entry_type', ['receivable', 'payment', 'credit_note', 'opening_balance', 'adjustment'])->default('receivable');
                $table->string('reference_type', 50)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->decimal('debit_amount', 12, 2)->default(0);
                $table->decimal('credit_amount', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->date('transaction_date');
                $table->date('due_date')->nullable();
                $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'])->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->enum('status', ['pending', 'completed', 'overdue', 'cancelled'])->default('completed');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['customer_id', 'transaction_date']);
                $table->index('reference_type');
                $table->index('status');
            });
            
            // Copy data with type conversion
            DB::statement("
                INSERT INTO customer_ledger_entries_temp 
                SELECT 
                    id, organization_id, store_id, customer_id,
                    CASE WHEN entry_type = 'sale' THEN 'receivable' ELSE entry_type END as entry_type,
                    reference_type, reference_id, reference_number,
                    debit_amount, credit_amount, balance,
                    description, notes, transaction_date, due_date,
                    payment_method, payment_reference, status, created_by,
                    created_at, updated_at, deleted_at
                FROM customer_ledger_entries
            ");
            
            // Drop old table and rename new one
            Schema::drop('customer_ledger_entries');
            Schema::rename('customer_ledger_entries_temp', 'customer_ledger_entries');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::create('customer_ledger_entries_temp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->enum('entry_type', ['sale', 'payment', 'credit_note', 'opening_balance', 'adjustment'])->default('sale');
                $table->string('reference_type', 50)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->decimal('debit_amount', 12, 2)->default(0);
                $table->decimal('credit_amount', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->text('description')->nullable();
                $table->text('notes')->nullable();
                $table->date('transaction_date');
                $table->date('due_date')->nullable();
                $table->enum('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'])->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->enum('status', ['pending', 'completed', 'overdue', 'cancelled'])->default('completed');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['customer_id', 'transaction_date']);
                $table->index('reference_type');
                $table->index('status');
            });
            
            DB::statement("
                INSERT INTO customer_ledger_entries_temp 
                SELECT 
                    id, organization_id, store_id, customer_id,
                    CASE WHEN entry_type = 'receivable' THEN 'sale' ELSE entry_type END as entry_type,
                    reference_type, reference_id, reference_number,
                    debit_amount, credit_amount, balance,
                    description, notes, transaction_date, due_date,
                    payment_method, payment_reference, status, created_by,
                    created_at, updated_at, deleted_at
                FROM customer_ledger_entries
            ");
            
            Schema::drop('customer_ledger_entries');
            Schema::rename('customer_ledger_entries_temp', 'customer_ledger_entries');
        }
    }
};
