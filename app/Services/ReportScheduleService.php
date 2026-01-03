<?php

namespace App\Services;

use App\Models\ReportSchedule;
use App\Models\ScheduledReportRun;
use App\Models\Notification;
use App\Models\Sale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Customer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
                Log::error("Failed to process schedule {$schedule->id}: " . $e->getMessage());
                
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
            if ($filePath && file_exists(storage_path('app/' . $filePath))) {
                $fileSize = filesize(storage_path('app/' . $filePath));
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
     * Generate report data based on type
     */
    protected function generateReportData(ReportSchedule $schedule): array
    {
        $params = $schedule->parameters ?? [];
        
        return match($schedule->report_type) {
            'sales' => $this->generateSalesReport($params),
            'inventory' => $this->generateInventoryReport($params),
            'customer_analytics' => $this->generateCustomerAnalyticsReport($params),
            'cashier_performance' => $this->generateCashierPerformanceReport($params),
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
        
        if (!empty($params['store_id'])) {
            $query->where('store_id', $params['store_id']);
        }

        $sales = $query->with(['store', 'customer', 'cashier'])->get();
        
        $totalSales = $sales->sum('total_amount');
        $totalTransactions = $sales->count();
        $averageTransaction = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;

        return [
            'title' => 'Sales Report',
            'period' => $dateRange[0]->format('M d, Y') . ' - ' . $dateRange[1]->format('M d, Y'),
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
        
        if (!empty($params['store_id'])) {
            $query->where('store_id', $params['store_id']);
        }

        $stockLevels = $query->get();
        
        $lowStock = $stockLevels->filter(fn($s) => $s->quantity <= $lowStockThreshold);
        $totalValue = $stockLevels->sum(fn($s) => $s->quantity * ($s->productVariant->cost_price ?? 0));

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
        
        $customers = Customer::with(['sales' => function($q) use ($dateRange) {
            $q->whereBetween('created_at', $dateRange);
        }])->get();

        $topCustomers = $customers->sortByDesc(function($customer) {
            return $customer->sales->sum('total_amount');
        })->take(20);

        return [
            'title' => 'Customer Analytics Report',
            'period' => $dateRange[0]->format('M d, Y') . ' - ' . $dateRange[1]->format('M d, Y'),
            'customers' => $topCustomers,
            'summary' => [
                'total_customers' => $customers->count(),
                'active_customers' => $customers->filter(fn($c) => $c->sales->count() > 0)->count(),
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
        $cashiers = \App\Models\User::whereHas('role', function($q) {
            $q->where('slug', 'cashier');
        })->with(['sales' => function($q) use ($dateRange) {
            $q->whereBetween('created_at', $dateRange);
        }])->get();

        $performance = $cashiers->map(function($cashier) {
            return [
                'cashier' => $cashier,
                'total_sales' => $cashier->sales->sum('total_amount'),
                'transaction_count' => $cashier->sales->count(),
                'average_transaction' => $cashier->sales->avg('total_amount'),
            ];
        })->sortByDesc('total_sales');

        return [
            'title' => 'Cashier Performance Report',
            'period' => $dateRange[0]->format('M d, Y') . ' - ' . $dateRange[1]->format('M d, Y'),
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
        $filename = str_replace(' ', '_', strtolower($schedule->name)) . '_' . $timestamp;

        // Generate PDF (if library is available)
        if (in_array($schedule->format, ['pdf', 'both'])) {
            try {
                // For now, create a simple text file until PDF library is installed
                $content = $this->generateSimpleReport($schedule, $reportData);
                $pdfPath = "reports/scheduled/{$filename}.txt";
                Storage::put($pdfPath, $content);
                $files['pdf'] = $pdfPath;
            } catch (\Exception $e) {
                Log::warning("PDF generation skipped: " . $e->getMessage());
            }
        }

        // Generate Excel (simplified - would use actual Excel export)
        if (in_array($schedule->format, ['excel', 'both'])) {
            $excelPath = "reports/scheduled/{$filename}.xlsx";
            // In production, use Maatwebsite Excel package
            $files['excel'] = $excelPath;
        }

        return $files;
    }

    /**
     * Send report emails to recipients
     */
    protected function sendReportEmails(ReportSchedule $schedule, array $files, array $reportData): void
    {
        foreach ($schedule->recipients as $recipient) {
            try {
                // Skip actual email sending if mail is not configured
                Log::info("Would send report email to {$recipient} for schedule: {$schedule->name}");
                
                // Uncomment below when mail is configured
                /*
                Mail::send('emails.scheduled-report', [
                    'schedule' => $schedule,
                    'reportData' => $reportData,
                ], function ($message) use ($recipient, $schedule, $files) {
                    $message->to($recipient)
                            ->subject($schedule->name . ' - ' . now()->format('M d, Y'));
                    
                    foreach ($files as $type => $path) {
                        if (Storage::exists($path)) {
                            $message->attach(storage_path('app/' . $path));
                        }
                    }
                });
                */
            } catch (\Exception $e) {
                Log::error("Failed to send report email to {$recipient}: " . $e->getMessage());
            }
        }
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

        // Default to last 30 days
        return [
            now()->subDays(30)->startOfDay(),
            now()->endOfDay(),
        ];
    }

    /**
     * Generate a simple text report (fallback when PDF library not available)
     */
    protected function generateSimpleReport(ReportSchedule $schedule, array $reportData): string
    {
        $content = "═══════════════════════════════════════════════════\n";
        $content .= strtoupper($reportData['title']) . "\n";
        $content .= "═══════════════════════════════════════════════════\n\n";
        $content .= "Period: {$reportData['period']}\n";
        $content .= "Generated: " . now()->format('M d, Y h:i A') . "\n";
        $content .= "Schedule: {$schedule->name}\n\n";

        if (isset($reportData['summary'])) {
            $content .= "SUMMARY\n";
            $content .= "-------\n";
            foreach ($reportData['summary'] as $key => $value) {
                $label = ucwords(str_replace('_', ' ', $key));
                if (is_numeric($value)) {
                    if (str_contains($key, 'amount') || str_contains($key, 'value')) {
                        $value = '₹' . number_format($value, 2);
                    } else {
                        $value = number_format($value);
                    }
                }
                $content .= "{$label}: {$value}\n";
            }
            $content .= "\n";
        }

        $content .= "Record Count: " . ($reportData['record_count'] ?? 0) . "\n";
        $content .= "\n";
        $content .= "This is a simplified text report. Install barryvdh/laravel-dompdf for PDF generation.\n";

        return $content;
    }
}

