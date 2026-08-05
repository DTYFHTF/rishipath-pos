<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\ReportSchedule;
use App\Models\Sale;
use App\Models\ScheduledReportRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportScheduleService
{
    /**
     * Process all due report schedules
     */
    public function processDueSchedules(): int
    {
        $dueSchedules = ReportSchedule::due()->get();
        $processedCount = 0;

        foreach ($dueSchedules as $schedule) {
            try {
                $this->generateAndSendReport($schedule);
                $processedCount++;
            } catch (\Exception $e) {
                Log::error("Failed to process schedule {$schedule->id}: ".$e->getMessage());

                // Create notification for failed schedule
                Notification::create([
                    'type' => 'report_failed',
                    'title' => 'Scheduled Report Failed',
                    'message' => "Failed to generate {$schedule->name}: {$e->getMessage()}",
                    'severity' => 'error',
                    'recipients' => $schedule->recipients,
                    'related_id' => $schedule->id,
                    'related_type' => ReportSchedule::class,
                ]);
            }
        }

        return $processedCount;
    }

    /**
     * Generate and send a scheduled report
     */
    public function generateAndSendReport(ReportSchedule $schedule): void
    {
        // Create run record
        $run = ScheduledReportRun::create([
            'report_schedule_id' => $schedule->id,
            'status' => 'pending',
        ]);

        try {
            $run->markAsStarted();

            // Generate report based on type
            $reportData = $this->generateReportData($schedule);

            // Save report files
            $files = $this->saveReportFiles($schedule, $reportData);

            // Send emails
            $this->sendReportEmails($schedule, $files, $reportData);

            // Mark as completed
            $filePath = $files['pdf'] ?? $files['excel'] ?? null;
            $fileSize = 0;
            if ($filePath && file_exists(storage_path('app/'.$filePath))) {
                $fileSize = filesize(storage_path('app/'.$filePath));
            }

            $run->markAsCompleted(
                $filePath,
                $fileSize,
                $reportData['record_count'] ?? 0
            );

            // Update schedule
            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->calculateNextRun(),
            ]);

        } catch (\Exception $e) {
            $run->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * The figures a schedule would report right now, without sending anything.
     *
     * Useful for previewing a report before committing to a schedule, and for
     * asserting on the numbers rather than on a rendered PDF.
     */
    public function reportDataFor(ReportSchedule $schedule): array
    {
        return $this->generateReportData($schedule);
    }

    /**
     * Generate report data based on type
     */
    protected function generateReportData(ReportSchedule $schedule): array
    {
        $params = $schedule->parameters ?? [];

        return match ($schedule->report_type) {
            'sales' => $this->generateSalesReport($params),
            'inventory' => $this->generateInventoryReport($params),
            'customer_analytics' => $this->generateCustomerAnalyticsReport($params),
            'cashier_performance' => $this->generateCashierPerformanceReport($params),
            'founders' => $this->generateFoundersReport($params),
            default => throw new \Exception("Unknown report type: {$schedule->report_type}"),
        };
    }

    /**
     * Generate sales report data
     */
    protected function generateSalesReport(array $params): array
    {
        $dateRange = $this->getDateRange($params);

        $query = Sale::whereBetween('created_at', $dateRange);

        if (! empty($params['store_id'])) {
            $query->where('store_id', $params['store_id']);
        }

        $sales = $query->with(['store', 'customer', 'cashier'])->get();

        $totalSales = $sales->sum('total_amount');
        $totalTransactions = $sales->count();
        $averageTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

        return [
            'title' => 'Sales Report',
            'period' => $dateRange[0]->format('M d, Y').' - '.$dateRange[1]->format('M d, Y'),
            'sales' => $sales,
            'summary' => [
                'total_sales' => $totalSales,
                'total_transactions' => $totalTransactions,
                'average_transaction' => $averageTransaction,
            ],
            'record_count' => $totalTransactions,
        ];
    }

    /**
     * Generate inventory report data
     */
    protected function generateInventoryReport(array $params): array
    {
        $lowStockThreshold = $params['low_stock_threshold'] ?? 10;

        $query = \App\Models\StockLevel::with(['productVariant.product', 'store']);

        if (! empty($params['store_id'])) {
            $query->where('store_id', $params['store_id']);
        }

        $stockLevels = $query->get();

        $lowStock = $stockLevels->filter(fn ($s) => $s->quantity <= $lowStockThreshold);
        $totalValue = $stockLevels->sum(fn ($s) => $s->quantity * ($s->productVariant->cost_price ?? 0));

        return [
            'title' => 'Inventory Report',
            'period' => now()->format('M d, Y'),
            'stockLevels' => $stockLevels,
            'low_stock' => $lowStock,
            'summary' => [
                'total_items' => $stockLevels->count(),
                'low_stock_count' => $lowStock->count(),
                'total_inventory_value' => $totalValue,
            ],
            'record_count' => $stockLevels->count(),
        ];
    }

    /**
     * Generate customer analytics report
     */
    protected function generateCustomerAnalyticsReport(array $params): array
    {
        $dateRange = $this->getDateRange($params);

        $customers = Customer::with(['sales' => function ($q) use ($dateRange) {
            $q->whereBetween('created_at', $dateRange);
        }])->get();

        $topCustomers = $customers->sortByDesc(function ($customer) {
            return $customer->sales->sum('total_amount');
        })->take(20);

        return [
            'title' => 'Customer Analytics Report',
            'period' => $dateRange[0]->format('M d, Y').' - '.$dateRange[1]->format('M d, Y'),
            'customers' => $topCustomers,
            'summary' => [
                'total_customers' => $customers->count(),
                'active_customers' => $customers->filter(fn ($c) => $c->sales->count() > 0)->count(),
            ],
            'record_count' => $topCustomers->count(),
        ];
    }

    /**
     * Generate cashier performance report
     */
    protected function generateCashierPerformanceReport(array $params): array
    {
        $dateRange = $this->getDateRange($params);

        // Get cashiers (users with cashier role)
        $cashiers = \App\Models\User::whereHas('role', function ($q) {
            $q->where('slug', 'cashier');
        })->with(['sales' => function ($q) use ($dateRange) {
            $q->whereBetween('created_at', $dateRange);
        }])->get();

        $performance = $cashiers->map(function ($cashier) {
            return [
                'cashier' => $cashier,
                'total_sales' => $cashier->sales->sum('total_amount'),
                'transaction_count' => $cashier->sales->count(),
                'average_transaction' => $cashier->sales->avg('total_amount'),
            ];
        })->sortByDesc('total_sales');

        return [
            'title' => 'Cashier Performance Report',
            'period' => $dateRange[0]->format('M d, Y').' - '.$dateRange[1]->format('M d, Y'),
            'performance' => $performance,
            'record_count' => $cashiers->count(),
        ];
    }

    /**
     * Save report files to storage
     */
    protected function saveReportFiles(ReportSchedule $schedule, array $reportData): array
    {
        $files = [];
        $timestamp = now()->format('Y-m-d_His');
        $filename = str_replace(' ', '_', strtolower($schedule->name)).'_'.$timestamp;

        // Excel export was never implemented — it only registered a path
        // without writing a file, which then failed to attach. The email body
        // carries the figures either way.
        if (! in_array($schedule->format, ['pdf', 'both'], true)) {
            return $files;
        }

        try {
            $pdf = Pdf::loadView('reports.scheduled.template', [
                'schedule' => $schedule,
                'reportData' => $reportData,
            ])->setPaper('a4');

            $path = "reports/scheduled/{$filename}.pdf";
            Storage::put($path, $pdf->output());
            $files['pdf'] = $path;
        } catch (\Throwable $e) {
            // A broken attachment must not stop the figures reaching anyone.
            Log::warning('Report PDF generation failed, sending without attachment: '.$e->getMessage());
        }

        return $files;
    }

    /**
     * Send report emails to recipients
     */
    protected function sendReportEmails(ReportSchedule $schedule, array $files, array $reportData): void
    {
        $sent = 0;

        foreach ($schedule->recipients ?? [] as $recipient) {
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                Log::warning("Skipping invalid report recipient '{$recipient}' on schedule {$schedule->id}");

                continue;
            }

            try {
                Mail::send('emails.scheduled-report', [
                    'schedule' => $schedule,
                    'reportData' => $reportData,
                ], function ($message) use ($recipient, $schedule, $files) {
                    $message->to($recipient)
                        ->subject($schedule->name.' — '.now()->format('D, d M Y'));

                    foreach ($files as $path) {
                        // Only attach what actually reached disk.
                        if (Storage::exists($path)) {
                            $message->attach(Storage::path($path));
                        }
                    }
                });

                $sent++;
            } catch (\Throwable $e) {
                // One bad address must not stop the other recipients.
                Log::error("Failed to send report email to {$recipient}: ".$e->getMessage());
            }
        }

        Log::info("Report '{$schedule->name}' emailed to {$sent} recipient(s)");
    }

    /**
     * Get date range from parameters
     */
    protected function getDateRange(array $params): array
    {
        if (isset($params['start_date']) && isset($params['end_date'])) {
            return [
                Carbon::parse($params['start_date']),
                Carbon::parse($params['end_date']),
            ];
        }

        // A daily report sent at 8am is reporting on the day that just closed,
        // so a rolling 30-day window would be the wrong answer for it.
        return match ($params['period'] ?? null) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfDay()],
            'this_month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->subDays(30)->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Daily founders summary — the whole business on one page.
     *
     * Deliberately not just sales: the questions a founder opens their phone
     * to answer are what came in, what it earned, who owes us, and what is
     * about to run out.
     */
    protected function generateFoundersReport(array $params): array
    {
        $dateRange = $this->getDateRange($params);

        $sales = Sale::whereBetween('created_at', $dateRange)
            ->where('status', 'completed')
            ->with(['items', 'customer'])
            ->get();

        $paid = $sales->where('payment_status', 'paid');
        $credit = $sales->where('payment_status', 'unpaid');

        // Revenue is paid sales only — credit is a promise, not money in.
        $revenue = (float) $paid->sum('total_amount');
        $cost = (float) $paid->flatMap->items->sum(
            fn ($item) => (float) $item->cost_price * (float) $item->quantity
        );

        $topProducts = $paid->flatMap->items
            ->groupBy('product_name')
            ->map(fn ($items, $name) => [
                'name' => $name,
                'quantity' => $items->sum('quantity'),
                'revenue' => $items->sum('total'),
            ])
            ->sortByDesc('revenue')
            ->take(5)
            ->values()
            ->all();

        $outstanding = (float) \App\Models\Sale::where('payment_status', 'unpaid')
            ->where('status', 'completed')
            ->sum('total_amount');

        $visits = \App\Models\RetailStoreVisit::whereBetween('visit_date', $dateRange)->count();
        $newStores = \App\Models\RetailStore::whereBetween('created_at', $dateRange)->count();

        $lowStock = \App\Models\StockLevel::with('productVariant.product')
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->where('quantity', '>', 0)
            ->limit(10)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->productVariant?->product?->name.' '.$s->productVariant?->pack_label,
                'quantity' => (int) $s->quantity,
            ])
            ->all();

        return [
            'title' => 'Founders Daily Report',
            'period' => $dateRange[0]->format('D, d M Y'),
            'sales' => $paid,
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
            'summary' => [
                'revenue' => round($revenue, 2),
                'transactions' => $paid->count(),
                'average_sale' => $paid->count() > 0 ? round($revenue / $paid->count(), 2) : 0,
                'gross_profit' => round($revenue - $cost, 2),
                'margin_percent' => $revenue > 0 ? round(100 * ($revenue - $cost) / $revenue, 1) : 0,
                'credit_sales_today' => $credit->count(),
                'credit_amount_today' => round((float) $credit->sum('total_amount'), 2),
                'total_outstanding' => round($outstanding, 2),
                'store_visits' => $visits,
                'new_stores' => $newStores,
            ],
            'record_count' => $paid->count(),
        ];
    }
}
