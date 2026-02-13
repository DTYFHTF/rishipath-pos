<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_order_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retail_store_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Contact info
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_country_code', 10)->nullable();
            $table->string('company_name')->nullable();
            $table->string('tax_number')->nullable();

            // Shipping address
            $table->text('shipping_address')->nullable();
            $table->string('shipping_area')->nullable();
            $table->string('shipping_landmark')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_pincode', 20)->nullable();
            $table->string('shipping_country')->default('Nepal');

            // Products & details
            $table->json('products')->nullable();
            $table->text('message')->nullable();
            $table->text('special_instructions')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('budget_range')->nullable();

            // Status
            $table->enum('status', ['new', 'contacted', 'quoted', 'closed'])->default('new');
            $table->text('admin_notes')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index('retail_store_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_order_inquiries');
    }
};
