<?php

namespace App\Console\Commands;

use App\Models\CustomerLedgerEntry;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateLedgerLogic extends Command
{
    protected $signature = 'ledger:update-logic';
    protected $description = 'Update ledger entries: credit sales = debit (owes), cash/card/UPI = credit (paid)';

    public function handle()
    {
        $this->info('Updating ledger entry logic...');
        $this->info('Current time (Nepal): ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        DB::beginTransaction();

        try {
            $entries = CustomerLedgerEntry::where('entry_type', 'sale')
                ->orderBy('id')
                ->get();

            $this->info("Found {$entries->count()} sale entries to update");
            $this->newLine();

            foreach ($entries as $entry) {
                $sale = Sale::find($entry->reference_id);
                
                if (!$sale) {
                    $this->warn("Sale not found for entry {$entry->id}");
                    continue;
                }
                
                $isCredit = $sale->payment_method === 'credit';
                
                if ($isCredit) {
                    // Credit sale: Customer OWES = DEBIT column
                    $entry->debit_amount = $sale->total_amount;
                    $entry->credit_amount = 0;
                    $type = 'DEBIT (owes)';
                } else {
                    // Cash/Card/UPI: Customer PAID = CREDIT column
                    $entry->debit_amount = 0;
                    $entry->credit_amount = $sale->total_amount;
                    $type = 'CREDIT (paid)';
                }
                
                $entry->saveQuietly();
                $this->line("✓ {$entry->reference_number} - {$sale->payment_method} → {$type}");
            }

            // Recalculate balances for each customer
            $this->newLine();
            $this->info('Recalculating balances...');
            
            $customers = Customer::all();
            foreach ($customers as $customer) {
                $customerEntries = CustomerLedgerEntry::where('customer_id', $customer->id)
                    ->orderBy('transaction_date')
                    ->orderBy('id')
                    ->get();
                
                if ($customerEntries->isEmpty()) continue;
                
                $balance = 0;
                foreach ($customerEntries as $entry) {
                    // Balance = Previous + Debit - Credit
                    // When customer owes (debit), balance increases
                    // When customer pays (credit), balance decreases
                    $balance = $balance + $entry->debit_amount - $entry->credit_amount;
                    $entry->balance = $balance;
                    $entry->saveQuietly();
                }
                
                $this->line("✓ Customer {$customer->name}: Balance = ₹{$balance}");
            }

            DB::commit();
            
            $this->newLine();
            $this->info('✅ Successfully updated all ledger entries!');
            
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error: {$e->getMessage()}");
            return 1;
        }
    }
}
