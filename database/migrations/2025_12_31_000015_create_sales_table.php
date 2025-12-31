<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->date('date');
            $table->time('time');
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->string('discount_reason')->nullable();
            $table->decimal('tax_amount', 10, 2);
            $table->json('tax_details')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'upi', 'card', 'esewa', 'khalti', 'other']);
            $table->enum('payment_status', ['paid', 'pending', 'partial', 'refunded'])->default('paid');
            $table->string('payment_reference')->nullable();
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->decimal('amount_change', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['completed', 'cancelled', 'refunded'])->default('completed');
            $table->boolean('is_synced')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('organization_id');
            $table->index('store_id');
            $table->index('terminal_id');
            $table->index('receipt_number');
            $table->index('date');
            $table->index('cashier_id');
            $table->index('is_synced');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
