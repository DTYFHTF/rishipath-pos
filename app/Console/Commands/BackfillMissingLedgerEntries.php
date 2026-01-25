<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMissingLedgerEntries extends Command
{
    protected $signature = 'ledger:backfill-missing';
    protected $description = 'Create missing ledger entries for sales with customers';

    public function handle()
    {
        $this->info('Finding sales without ledger entries...');

        $salesWithoutEntries = Sale::whereNotNull('customer_id')
            ->whereNotIn('id', function($query) {
                $query->select('reference_id')
                    ->from('customer_ledger_entries')
                    ->where('reference_type', 'Sale');
            })
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $this->info("Found {$salesWithoutEntries->count()} sales without ledger entries");

        if ($salesWithoutEntries->isEmpty()) {
            $this->info('✅ All sales have ledger entries!');
            return 0;
        }

        DB::beginTransaction();

        try {
            $created = 0;
            foreach ($salesWithoutEntries as $sale) {
                $customer = $sale->customer;
                $previousBalance = CustomerLedgerEntry::getCustomerBalance($customer->id);
                
                // Handle payment method
                $allowedMethods = ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'credit'];
                $paymentMethod = in_array($sale->payment_method, $allowedMethods) ? $sale->payment_method : null;
                
                $isCredit = $sale->payment_method === 'credit';
                
                CustomerLedgerEntry::create([
                    'organization_id' => $sale->organization_id,
                    'store_id' => $sale->store_id,
                    'customer_id' => $customer->id,
                    'entry_type' => 'sale',
                    'reference_type' => 'Sale',
                    'reference_id' => $sale->id,
                    'reference_number' => $sale->invoice_number,
                    'debit_amount' => $sale->total_amount,
                    'credit_amount' => $isCredit ? 0 : $sale->total_amount,
                    'balance' => $isCredit ? ($previousBalance + $sale->total_amount) : $previousBalance,
                    'description' => ($isCredit ? 'Credit Sale' : 'Sale') . " - Invoice #{$sale->invoice_number}",
                    'transaction_date' => $sale->date,
                    'due_date' => $isCredit ? now()->addDays(30) : null,
                    'payment_method' => $paymentMethod,
                    'status' => $isCredit ? 'pending' : 'completed',
                    'created_by' => $sale->cashier_id,
                ]);

                $created++;
                $this->line("✓ {$sale->invoice_number} - {$sale->payment_method} - ₹{$sale->total_amount}");
            }

            DB::commit();
            $this->info("\n✅ Created {$created} ledger entries");
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
