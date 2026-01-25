<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support adding columns with CHECK constraints directly
        // So we need to recreate the table
        
        // Create new table with polymorphic columns
        Schema::create('customer_ledger_entries_new', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            
            // Polymorphic relationship - can be Customer or Supplier
            $table->morphs('ledgerable'); // Creates ledgerable_type and ledgerable_id
            
            // Keep customer_id temporarily for migration (will be nullable)
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            
            $table->enum('entry_type', ['receivable', 'payment', 'credit_note', 'debit_note', 'adjustment', 'opening_balance']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance (morphs() already creates index for ledgerable)
            $table->index('transaction_date');
            $table->index('reference_number');
        });
        
        // Copy data from old table, setting ledgerable to Customer
        DB::statement("
            INSERT INTO customer_ledger_entries_new (
                id, organization_id, store_id, ledgerable_type, ledgerable_id, customer_id,
                entry_type, reference_type, reference_id, reference_number,
                debit_amount, credit_amount, balance, description, notes,
                transaction_date, due_date, payment_method, payment_reference,
                status, created_by, created_at, updated_at, deleted_at
            )
            SELECT 
                id, organization_id, store_id, 'App\\Models\\Customer', customer_id, customer_id,
                entry_type, reference_type, reference_id, reference_number,
                debit_amount, credit_amount, balance, description, notes,
                transaction_date, due_date, payment_method, payment_reference,
                status, created_by, created_at, updated_at, deleted_at
            FROM customer_ledger_entries
        ");
        
        // Drop old table
        Schema::dropIfExists('customer_ledger_entries');
        
        // Rename new table
        Schema::rename('customer_ledger_entries_new', 'customer_ledger_entries');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Create old table structure
        Schema::create('customer_ledger_entries_old', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('entry_type', ['receivable', 'payment', 'credit_note', 'debit_note', 'adjustment', 'opening_balance']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        
        // Copy data back (only Customer entries)
        DB::statement("
            INSERT INTO customer_ledger_entries_old (
                id, organization_id, store_id, customer_id,
                entry_type, reference_type, reference_id, reference_number,
                debit_amount, credit_amount, balance, description, notes,
                transaction_date, due_date, payment_method, payment_reference,
                status, created_by, created_at, updated_at, deleted_at
            )
            SELECT 
                id, organization_id, store_id, ledgerable_id,
                entry_type, reference_type, reference_id, reference_number,
                debit_amount, credit_amount, balance, description, notes,
                transaction_date, due_date, payment_method, payment_reference,
                status, created_by, created_at, updated_at, deleted_at
            FROM customer_ledger_entries
            WHERE ledgerable_type = 'App\\\\Models\\\\Customer'
        ");
        
        Schema::dropIfExists('customer_ledger_entries');
        Schema::rename('customer_ledger_entries_old', 'customer_ledger_entries');
    }
};
