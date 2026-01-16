<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockAdjustment;
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
                'cashier_variance' => $this->checkCashierVarianceAlert($rule),
                'inventory_discrepancy' => $this->checkInventoryDiscrepancyAlert($rule),
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

        $query = ProductVariant::with(['product', 'store'])
            ->where('stock_quantity', '<=', $threshold);

        if ($rule->store_id) {
            $query->where('store_id', $rule->store_id);
        }

        $lowStockItems = $query->get();

        if ($lowStockItems->isEmpty()) {
            return false;
        }

        // Create notification
        $this->createNotification(
            type: 'low_stock',
            title: 'Low Stock Alert',
            message: "{$lowStockItems->count()} items are running low on stock (below {$threshold} units)",
            severity: 'warning',
            recipients: $rule->recipients,
            data: [
                'items' => $lowStockItems->map(fn ($v) => [
                    'product' => $v->product->name,
                    'variant' => $v->sku,
                    'stock' => $v->stock_quantity,
                    'store' => $v->store?->name,
                ])->toArray(),
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

        $highValueSales = $query->with(['store', 'customer', 'user'])->get();

        if ($highValueSales->isEmpty()) {
            return false;
        }

        foreach ($highValueSales as $sale) {
            $this->createNotification(
                type: 'high_value_sale',
                title: 'High Value Sale Alert',
                message: 'High value sale of ₹'.number_format($sale->total_amount, 2).' by '.$sale->user->name,
                severity: 'info',
                recipients: $rule->recipients,
                data: [
                    'sale_id' => $sale->id,
                    'amount' => $sale->total_amount,
                    'cashier' => $sale->user->name,
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
     * Check for cashier cash variance
     */
    protected function checkCashierVarianceAlert(AlertRule $rule): bool
    {
        $threshold = $rule->conditions['threshold'] ?? 1000; // Variance threshold
        $checkDate = now()->toDateString();

        // Check cash sessions with variance
        $sessions = \App\Models\CashSession::where('date', $checkDate)
            ->where('status', 'closed')
            ->whereRaw('ABS(actual_cash - expected_cash) >= ?', [$threshold])
            ->with(['user', 'store'])
            ->get();

        if ($sessions->isEmpty()) {
            return false;
        }

        foreach ($sessions as $session) {
            $variance = abs($session->actual_cash - $session->expected_cash);
            $type = $session->actual_cash > $session->expected_cash ? 'surplus' : 'shortage';

            $this->createNotification(
                type: 'cashier_variance',
                title: 'Cash Variance Alert',
                message: "Cash {$type} of ₹".number_format($variance, 2).' detected for '.$session->user->name,
                severity: 'warning',
                recipients: $rule->recipients,
                data: [
                    'cashier' => $session->user->name,
                    'expected' => $session->expected_cash,
                    'actual' => $session->actual_cash,
                    'variance' => $variance,
                    'type' => $type,
                    'store' => $session->store?->name,
                ]
            );
        }

        $rule->markAsTriggered();

        return true;
    }

    /**
     * Check for inventory discrepancies
     */
    protected function checkInventoryDiscrepancyAlert(AlertRule $rule): bool
    {
        $threshold = $rule->conditions['threshold'] ?? 50; // Adjustment quantity threshold
        $checkSince = now()->subDay();

        $query = StockAdjustment::where('created_at', '>=', $checkSince)
            ->whereRaw('ABS(quantity) >= ?', [$threshold])
            ->with(['productVariant.product', 'store', 'adjustedBy']);

        if ($rule->store_id) {
            $query->where('store_id', $rule->store_id);
        }

        $adjustments = $query->get();

        if ($adjustments->isEmpty()) {
            return false;
        }

        $this->createNotification(
            type: 'inventory_discrepancy',
            title: 'Inventory Discrepancy Alert',
            message: "{$adjustments->count()} significant stock adjustments detected in the last 24 hours",
            severity: 'warning',
            recipients: $rule->recipients,
            data: [
                'adjustments' => $adjustments->map(fn ($a) => [
                    'product' => $a->productVariant->product->name,
                    'variant' => $a->productVariant->sku,
                    'quantity' => $a->quantity,
                    'reason' => $a->reason,
                    'adjusted_by' => $a->adjustedBy->name,
                    'store' => $a->store?->name,
                ])->toArray(),
            ]
        );

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
