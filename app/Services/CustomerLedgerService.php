<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class CustomerLedgerService
{
    /**
     * Record a sale in customer ledger
     */
    public function recordSale(Sale $sale): CustomerLedgerEntry
    {
        return CustomerLedgerEntry::createSaleEntry($sale);
    }

    /**
     * Record a payment from customer
     */
    public function recordPayment(Customer $customer, array $data): CustomerLedgerEntry
    {
        DB::beginTransaction();
        
        try {
            $entry = CustomerLedgerEntry::createPaymentEntry($customer, $data);
            
            // Update pending sales if applicable
            $this->allocatePaymentToSales($customer, $data['amount']);
            
            DB::commit();
            
            return $entry;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Allocate payment to pending sales (FIFO)
     */
    protected function allocatePaymentToSales(Customer $customer, float $amount): void
    {
        $pendingEntries = CustomerLedgerEntry::forCustomer($customer->id)
            ->where('status', 'pending')
            ->where('entry_type', 'sale')
            ->orderBy('transaction_date', 'asc')
            ->get();

        $remainingAmount = $amount;

        foreach ($pendingEntries as $entry) {
            if ($remainingAmount <= 0) {
                break;
            }

            if ($remainingAmount >= $entry->debit_amount) {
                // Full payment
                $entry->update(['status' => 'completed']);
                $remainingAmount -= $entry->debit_amount;
            } else {
                // Partial payment - still pending
                $remainingAmount = 0;
            }
        }
    }

    /**
     * Get customer statement for a period
     */
    public function getCustomerStatement(
        int $customerId,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $customer = Customer::find($customerId);

        if (!$customer) {
            throw new \Exception("Customer not found");
        }

        $query = CustomerLedgerEntry::forCustomer($customerId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc');

        if ($startDate) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('transaction_date', '<=', $endDate);
        }

        $entries = $query->get();

        // Calculate opening balance
        $openingBalance = 0;
        if ($startDate) {
            $openingBalance = CustomerLedgerEntry::forCustomer($customerId)
                ->where('transaction_date', '<', $startDate)
                ->orderBy('created_at', 'desc')
                ->first()
                ->balance ?? 0;
        }

        return [
            'customer' => $customer,
            'opening_balance' => $openingBalance,
            'entries' => $entries,
            'closing_balance' => $entries->last()->balance ?? $openingBalance,
            'total_debit' => $entries->sum('debit_amount'),
            'total_credit' => $entries->sum('credit_amount'),
            'outstanding' => CustomerLedgerEntry::getCustomerOutstanding($customerId),
        ];
    }

    /**
     * Get aging report for customer
     */
    public function getAgingReport(int $customerId): array
    {
        $entries = CustomerLedgerEntry::forCustomer($customerId)
            ->where('status', 'pending')
            ->where('entry_type', 'sale')
            ->get();

        $aging = [
            'current' => 0,
            '1-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0,
        ];

        foreach ($entries as $entry) {
            $bucket = $entry->getAgingBucket();
            $aging[$this->mapAgingBucket($bucket)] += $entry->debit_amount;
        }

        return $aging;
    }

    protected function mapAgingBucket(string $bucket): string
    {
        return match ($bucket) {
            'Current' => 'current',
            '1-30 days' => '1-30',
            '31-60 days' => '31-60',
            '61-90 days' => '61-90',
            '90+ days' => '90+',
            default => 'current',
        };
    }

    /**
     * Create opening balance entry
     */
    public function createOpeningBalance(Customer $customer, float $amount, string $date, string $notes = null): CustomerLedgerEntry
    {
        return CustomerLedgerEntry::create([
            'organization_id' => $customer->organization_id,
            'customer_id' => $customer->id,
            'entry_type' => 'opening_balance',
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'balance' => $amount,
            'description' => 'Opening Balance',
            'notes' => $notes,
            'transaction_date' => $date,
            'status' => $amount > 0 ? 'pending' : 'completed',
            'created_by' => auth()->id(),
        ]);
    }
}
