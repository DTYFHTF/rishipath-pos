<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['purchase', 'payment', 'return', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('organization_id');
            $table->index('supplier_id');
            $table->index('purchase_id');
            $table->index('type');
            $table->index('created_at');
        });

        // Add balance column to suppliers
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('current_balance', 12, 2)->default(0)->after('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
        
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('current_balance');
        });
    }
};
