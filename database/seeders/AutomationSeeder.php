<?php

namespace Database\Seeders;

use App\Models\AlertRule;
use App\Models\Organization;
use App\Models\ReportSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The scheduled work the business actually wants: a daily founders report and
 * the handful of alerts worth interrupting someone for.
 *
 * Both tables were empty in production, so `alerts:check` and
 * `reports:process-scheduled` ran on their schedules doing nothing at all.
 */
class AutomationSeeder extends Seeder
{
    /** Where the daily numbers go. */
    private const FOUNDERS_EMAIL = 'info@shuddhidham.com';

    public function run(): void
    {
        $organization = Organization::first();

        if (! $organization) {
            $this->command?->warn('No organization — skipping automation seed.');

            return;
        }

        $owner = User::where('organization_id', $organization->id)
            ->whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))
            ->first()
            ?? User::where('organization_id', $organization->id)->first();

        $this->seedReports($owner?->id);
        $this->seedAlerts($owner?->id);
    }

    private function seedReports(?int $ownerId): void
    {
        // Yesterday, not today: it lands at 8am reporting on the day that closed.
        $this->report('Founders Daily Report', [
            'report_type' => 'founders',
            'frequency' => 'daily',
            'parameters' => ['period' => 'yesterday'],
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);

        $this->report('Weekly Sales Summary', [
            'report_type' => 'sales',
            'frequency' => 'weekly',
            'parameters' => ['period' => 'this_week'],
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);

        $this->report('Monthly Inventory Review', [
            'report_type' => 'inventory',
            'frequency' => 'monthly',
            'parameters' => ['low_stock_threshold' => 10],
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);
    }

    private function report(string $name, array $attributes): void
    {
        $schedule = ReportSchedule::firstOrNew(['name' => $name]);

        // Only fill scheduling state on first creation — re-seeding must not
        // reset next_run_at and cause a duplicate send.
        if (! $schedule->exists) {
            $schedule->fill([
                'format' => 'pdf',
                'active' => true,
                'next_run_at' => now()->addMinutes(5),
            ]);
        }

        $schedule->fill($attributes)->save();
    }

    private function seedAlerts(?int $ownerId): void
    {
        // Only the three alert types the service can actually evaluate.
        $this->alert('Low stock', [
            'type' => 'low_stock',
            'conditions' => ['threshold' => 10],
            'frequency' => 'daily',
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);

        $this->alert('Large sale', [
            'type' => 'high_value_sale',
            'conditions' => ['threshold' => 25000],
            'frequency' => 'hourly',
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);

        $this->alert('Daily sales target hit', [
            'type' => 'sales_target',
            'conditions' => ['target' => 50000, 'period' => 'daily'],
            'frequency' => 'daily',
            'recipients' => [self::FOUNDERS_EMAIL],
            'created_by' => $ownerId,
        ]);
    }

    private function alert(string $name, array $attributes): void
    {
        $rule = AlertRule::firstOrNew(['name' => $name]);

        if (! $rule->exists) {
            $rule->fill(['active' => true, 'trigger_count' => 0]);
        }

        $rule->fill($attributes)->save();
    }
}
