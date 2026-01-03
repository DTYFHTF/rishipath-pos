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
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            
            // Session identification
            $table->string('session_key')->unique(); // Unique identifier for this cart
            $table->string('session_name')->nullable(); // Optional name (e.g., "Customer 1", "Walk-in")
            
            // Cart data
            $table->json('cart_items')->nullable(); // Stored cart items
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Session status
            $table->enum('status', ['active', 'parked', 'completed', 'cancelled'])->default('active');
            $table->timestamp('parked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Additional info
            $table->text('notes')->nullable();
            $table->integer('display_order')->default(0); // For sorting tabs
            
            $table->timestamps();
            
            // Indexes
            $table->index(['cashier_id', 'status']);
            $table->index(['store_id', 'status']);
            $table->index('session_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
