<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\StockLevel;
use Illuminate\Console\Command;

class SendLowStockNotifications extends Command
{
    protected $signature = 'notifications:low-stock';
    protected $description = 'Send notifications for low stock items';

    public function handle(): int
    {
        $this->info('Checking for low stock items...');

        $lowStockItems = StockLevel::with(['productVariant.product', 'store'])
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->where('quantity', '>', 0)
            ->get();

        if ($lowStockItems->isEmpty()) {
            $this->info('No low stock items found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$lowStockItems->count()} low stock items.");

        foreach ($lowStockItems as $stockLevel) {
            // Check if notification already sent recently (within last 24 hours)
            $recentNotification = Notification::where('type', 'low_stock')
                ->where('related_type', 'App\\Models\\StockLevel')
                ->where('related_id', $stockLevel->id)
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($recentNotification) {
                continue;
            }

            $product = $stockLevel->productVariant->product;
            $variant = $stockLevel->productVariant;
            $store = $stockLevel->store;

            Notification::create([
                'type' => 'low_stock',
                'title' => 'Low Stock Alert',
                'message' => "Low stock for {$product->name} ({$variant->sku}) at {$store->name}. Current: {$stockLevel->quantity}, Reorder Level: {$stockLevel->reorder_level}",
                'severity' => 'warning',
                'data' => [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'store_id' => $store->id,
                    'current_quantity' => $stockLevel->quantity,
                    'reorder_level' => $stockLevel->reorder_level,
                ],
                'recipients' => ['admin', 'store_manager'],
                'related_type' => 'App\\Models\\StockLevel',
                'related_id' => $stockLevel->id,
                'triggered_by' => null,
            ]);

            $this->line("✓ Notification created for {$product->name} at {$store->name}");
        }

        $this->info('✅ Low stock notifications completed.');
        return Command::SUCCESS;
    }
}
