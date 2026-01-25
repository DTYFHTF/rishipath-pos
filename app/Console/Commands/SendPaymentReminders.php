<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Supplier;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    protected $signature = 'notifications:payment-reminders';
    protected $description = 'Send payment reminder notifications for overdue accounts';

    public function handle(): int
    {
        $this->info('Checking for overdue payments...');

        // Check customer balances
        $overdueCustomers = Customer::where('balance', '<', 0)
            ->where('balance', '>', -50000) // Only significant amounts
            ->get();

        foreach ($overdueCustomers as $customer) {
            // Check if reminder sent in last 7 days
            $recentReminder = Notification::where('type', 'payment_reminder')
                ->where('related_type', 'App\\Models\\Customer')
                ->where('related_id', $customer->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if ($recentReminder) {
                continue;
            }

            $balance = abs($customer->balance);
            
            Notification::create([
                'type' => 'payment_reminder',
                'title' => 'Customer Payment Overdue',
                'message' => "{$customer->name} has an outstanding balance of ₹{$balance}. Last purchase: {$customer->last_purchase_date}",
                'severity' => 'info',
                'data' => [
                    'customer_id' => $customer->id,
                    'balance' => $customer->balance,
                    'last_purchase_date' => $customer->last_purchase_date,
                ],
                'recipients' => ['admin', 'accounts'],
                'related_type' => 'App\\Models\\Customer',
                'related_id' => $customer->id,
                'triggered_by' => null,
            ]);

            $this->line("✓ Payment reminder for {$customer->name} (₹{$balance})");
        }

        // Check supplier balances
        $overdueSuppliers = Supplier::where('balance', '<', 0)
            ->where('balance', '>', -100000)
            ->get();

        foreach ($overdueSuppliers as $supplier) {
            $recentReminder = Notification::where('type', 'payment_reminder')
                ->where('related_type', 'App\\Models\\Supplier')
                ->where('related_id', $supplier->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if ($recentReminder) {
                continue;
            }

            $balance = abs($supplier->balance);
            
            Notification::create([
                'type' => 'payment_reminder',
                'title' => 'Supplier Payment Overdue',
                'message' => "Payment due to {$supplier->name}: ₹{$balance}",
                'severity' => 'warning',
                'data' => [
                    'supplier_id' => $supplier->id,
                    'balance' => $supplier->balance,
                ],
                'recipients' => ['admin', 'accounts', 'purchase_manager'],
                'related_type' => 'App\\Models\\Supplier',
                'related_id' => $supplier->id,
                'triggered_by' => null,
            ]);

            $this->line("✓ Payment reminder for {$supplier->name} (₹{$balance})");
        }

        $this->info('✅ Payment reminders completed.');
        return Command::SUCCESS;
    }
}
