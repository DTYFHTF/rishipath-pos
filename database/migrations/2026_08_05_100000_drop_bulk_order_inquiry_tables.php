<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulk order inquiries and their quotations are removed.
     *
     * The feature staged an order before a sale existed: log the ask, mark it
     * contacted, issue a quotation, close it. In practice orders are taken
     * either as a wholesale POS sale (dealer pricing, one step) or noted on a
     * retail store visit via order_placed / order_value. Neither route ever
     * needed the staging table, and all three tables held zero rows in
     * production.
     *
     * The invoices tables go with it: quotations were the only thing that ever
     * wrote to them. Sale receipts are rendered straight to PDF from the sale
     * and never persisted an Invoice row.
     */
    public function up(): void
    {
        // invoice_lines first — it has a foreign key onto invoices.
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bulk_order_inquiries');
    }

    /**
     * Deliberately irreversible. Recreating empty tables would restore the
     * schema but not the models, resources or service that gave them meaning —
     * restore those from git history instead.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Restore the bulk order inquiry feature from git history rather than rolling this back.'
        );
    }
};
