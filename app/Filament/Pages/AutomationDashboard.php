<?php

namespace App\Filament\Pages;

use App\Models\AlertRule;
use App\Models\Notification;
use App\Models\ReportSchedule;
use App\Models\ScheduledReportRun;
use Filament\Pages\Page;

class AutomationDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Automation';

    protected static ?string $navigationGroup = 'Reports & Alerts';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.automation-dashboard';

    protected static ?string $title = 'Report Scheduling & Automation';

    /**
     * Get statistics for the dashboard
     */
    public function getStats(): array
    {
        $activeSchedules = ReportSchedule::active()->count();
        $totalSchedules = ReportSchedule::count();

        $activeAlerts = AlertRule::active()->count();
        $totalAlerts = AlertRule::count();

        $unsentNotifications = Notification::unsent()->count();
        $criticalAlerts = Notification::bySeverity('critical')
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $recentRuns = ScheduledReportRun::where('created_at', '>=', now()->subWeek())
            ->count();
        $successRate = $this->getSuccessRate();

        return [
            'active_schedules' => $activeSchedules,
            'total_schedules' => $totalSchedules,
            'active_alerts' => $activeAlerts,
            'total_alerts' => $totalAlerts,
            'unsent_notifications' => $unsentNotifications,
            'critical_alerts' => $criticalAlerts,
            'recent_runs' => $recentRuns,
            'success_rate' => $successRate,
        ];
    }

    /**
     * Get recent scheduled report runs
     */
    public function getRecentRuns(): array
    {
        return ScheduledReportRun::with(['schedule'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($run) {
                return [
                    'id' => $run->id,
                    'schedule_name' => $run->schedule->name,
                    'status' => $run->status,
                    'created_at' => $run->created_at,
                    'duration' => $run->duration,
                    'records' => $run->records_processed,
                    'file_size' => $run->formatted_file_size,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent notifications
     */
    public function getRecentNotifications(): array
    {
        return Notification::orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'severity' => $notification->severity,
                    'severity_color' => $notification->severity_color,
                    'sent' => $notification->sent,
                    'created_at' => $notification->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get upcoming scheduled reports
     */
    public function getUpcomingSchedules(): array
    {
        return ReportSchedule::active()
            ->whereNotNull('next_run_at')
            ->orderBy('next_run_at')
            ->take(5)
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'report_type' => $schedule->report_type_name,
                    'frequency' => ucfirst($schedule->frequency),
                    'next_run_at' => $schedule->next_run_at,
                    'recipients_count' => count($schedule->recipients),
                ];
            })
            ->toArray();
    }

    /**
     * Get alert rule summary
     */
    public function getAlertSummary(): array
    {
        $rules = AlertRule::active()->get();

        $byType = $rules->groupBy('type')->map(fn ($group) => $group->count())->toArray();
        $byFrequency = $rules->groupBy('frequency')->map(fn ($group) => $group->count())->toArray();

        return [
            'by_type' => $byType,
            'by_frequency' => $byFrequency,
            'most_triggered' => AlertRule::active()
                ->orderBy('trigger_count', 'desc')
                ->take(5)
                ->get()
                ->map(fn ($rule) => [
                    'name' => $rule->name,
                    'count' => $rule->trigger_count,
                    'last_triggered' => $rule->last_triggered_at,
                ])
                ->toArray(),
        ];
    }

    /**
     * Calculate success rate for scheduled reports
     */
    protected function getSuccessRate(): float
    {
        $total = ScheduledReportRun::where('created_at', '>=', now()->subWeek())->count();

        if ($total === 0) {
            return 100.0;
        }

        $successful = ScheduledReportRun::completed()
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        return round(($successful / $total) * 100, 1);
    }
}
