<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\Sale;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertService
{
    /**
     * Check all active alert rules
     */
    public function checkAllAlerts(): int
    {
        $alertsFired = 0;
        $rules = AlertRule::active()->get();

        foreach ($rules as $rule) {
            if ($rule->shouldCheck()) {
                $triggered = $this->checkAlert($rule);
                if ($triggered) {
                    $alertsFired++;
                }
            }
        }

        return $alertsFired;
    }

    /**
     * Check a specific alert rule
     */
    public function checkAlert(AlertRule $rule): bool
    {
        try {
            $triggered = match ($rule->type) {
                'low_stock' => $this->checkLowStockAlert($rule),
                'high_value_sale' => $this->checkHighValueSaleAlert($rule),
                'sales_target' => $this->checkSalesTargetAlert($rule),
                default => false,
            };

            return $triggered;
        } catch (\Exception $e) {
            Log::error("Failed to check alert {$rule->id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Check for low stock items
     */
    protected function checkLowStockAlert(AlertRule $rule): bool
    {
        $threshold = $rule->conditions['threshold'] ?? 10;

        // Stock lives on stock_levels, per store — product_variants has no
        // stock_quantity or store_id, so the old query could only ever error.
        $query = \App\Models\StockLevel::with(['productVariant.product', 'store'])
            ->where('quantity', '<=', $threshold);

        if ($rule->store_id) {
            $query->where('store_id', $rule->store_id);
        }

        $lowStockItems = $query->get()
            ->filter(fn ($level) => $level->productVariant?->active);

        if ($lowStockItems->isEmpty()) {
            return false;
        }

        $this->createNotification(
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: "{$lowStockItems->count()} items are at or below {$threshold} units",
            severity: 'warning',
            recipients: $rule->recipients,
            data: [
                'items' => $lowStockItems->map(fn ($level) => [
                    'product' => $level->productVariant?->product?->name,
                    'variant' => $level->productVariant?->pack_label,
                    'sku' => $level->productVariant?->sku,
                    'stock' => (int) $level->quantity,
                    'store' => $level->store?->name,
                ])->values()->toArray(),
            ]
        );

        $rule->markAsTriggered();

        return true;
    }

    /**
     * Check for high value sales
     */
    protected function checkHighValueSaleAlert(AlertRule $rule): bool
    {
        $threshold = $rule->conditions['threshold'] ?? 10000;
        $checkSince = now()->subHour(); // Check last hour

        $query = Sale::where('total_amount', '>=', $threshold)
            ->where('created_at', '>=', $checkSince);

        if ($rule->store_id) {
            $query->where('store_id', $rule->store_id);
        }

        // Sale belongs to a cashier, not a "user" — the old relation name meant
        // this threw the moment a large sale actually happened.
        $highValueSales = $query->with(['store', 'customer', 'cashier'])->get();

        if ($highValueSales->isEmpty()) {
            return false;
        }

        foreach ($highValueSales as $sale) {
            $cashier = $sale->cashier?->name ?? 'unknown cashier';

            $this->createNotification(
                type: 'high_value_sale',
                title: 'High Value Sale Alert',
                message: 'High value sale of ₹'.number_format($sale->total_amount, 2).' by '.$cashier,
                severity: 'info',
                recipients: $rule->recipients,
                data: [
                    'sale_id' => $sale->id,
                    'amount' => $sale->total_amount,
                    'cashier' => $cashier,
                    'customer' => $sale->customer?->name,
                    'store' => $sale->store?->name,
                ],
                relatedId: $sale->id,
                relatedType: Sale::class
            );
        }

        $rule->markAsTriggered();

        return true;
    }

    /**
     * Check for sales target achievement
     */
    protected function checkSalesTargetAlert(AlertRule $rule): bool
    {
        $target = $rule->conditions['target'] ?? 100000;
        $period = $rule->conditions['period'] ?? 'daily'; // daily, weekly, monthly

        $dateRange = match ($period) {
            'daily' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'monthly' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        $query = Sale::whereBetween('created_at', $dateRange);

        if ($rule->store_id) {
            $query->where('store_id', $rule->store_id);
        }

        $totalSales = $query->sum('total_amount');
        $percentage = ($totalSales / $target) * 100;

        // Alert if target achieved
        if ($totalSales >= $target) {
            $this->createNotification(
                type: 'sales_target',
                title: 'Sales Target Achieved! 🎉',
                message: 'Sales target of ₹'.number_format($target, 2).' achieved! Current sales: ₹'.number_format($totalSales, 2),
                severity: 'info',
                recipients: $rule->recipients,
                data: [
                    'target' => $target,
                    'actual' => $totalSales,
                    'percentage' => $percentage,
                    'period' => $period,
                ]
            );

            $rule->markAsTriggered();

            return true;
        }

        return false;
    }

    /**
     * Create a notification
     */
    protected function createNotification(
        string $type,
        string $title,
        string $message,
        string $severity,
        array $recipients,
        array $data = [],
        ?int $relatedId = null,
        ?string $relatedType = null
    ): Notification {
        $notification = Notification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'recipients' => $recipients,
            'data' => $data,
            'related_id' => $relatedId,
            'related_type' => $relatedType,
        ]);

        // Send notification emails
        $this->sendNotificationEmails($notification);

        return $notification;
    }

    /**
     * Send notification emails
     */
    protected function sendNotificationEmails(Notification $notification): void
    {
        foreach ($notification->recipients as $recipient) {
            try {
                // Skip actual email sending if mail is not configured
                Log::info("Would send alert notification to {$recipient}: {$notification->title}");
                $notification->markAsSent();

                // Uncomment below when mail is configured
                /*
                Mail::send('emails.alert-notification', [
                    'notification' => $notification,
                ], function ($message) use ($recipient, $notification) {
                    $message->to($recipient)
                            ->subject($notification->title);
                });

                $notification->markAsSent();
                */
            } catch (\Exception $e) {
                Log::error("Failed to send notification email to {$recipient}: ".$e->getMessage());
                $notification->markAsFailed($e->getMessage());
            }
        }
    }

    /**
     * Process unsent notifications
     */
    public function processUnsentNotifications(): int
    {
        $unsent = Notification::unsent()
            ->where('created_at', '>=', now()->subDay())
            ->get();

        $processed = 0;
        foreach ($unsent as $notification) {
            $this->sendNotificationEmails($notification);
            $processed++;
        }

        return $processed;
    }
}
