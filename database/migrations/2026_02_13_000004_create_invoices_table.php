<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generic invoices table — supports types: invoice, quotation, credit_note, proforma
        // Polymorphic: invoiceable_type + invoiceable_id links to Sale, BulkOrderInquiry, etc.
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->enum('type', ['invoice', 'quotation', 'credit_note', 'proforma'])->default('invoice');
            $table->enum('status', ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'void'])->default('draft');

            // Polymorphic relation (Sale, BulkOrderInquiry, etc.)
            $table->nullableMorphs('invoiceable');

            // Customer / recipient info
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('retail_store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();

            // Financials
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->string('discount_type')->nullable(); // percentage, fixed
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->json('tax_details')->nullable();
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('amount_due', 14, 2)->default(0);
            $table->string('currency', 10)->default('NPR');

            // Dates
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();

            // Payment info
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();

            // Additional
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'status']);
            $table->index('issue_date');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot at time of invoice (DRY: no live references)
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->text('item_description')->nullable();

            $table->decimal('quantity', 12, 2);
            $table->string('unit')->nullable(); // pcs, kg, box, etc.
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0); // percentage
            $table->decimal('line_total', 14, 2);

            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
