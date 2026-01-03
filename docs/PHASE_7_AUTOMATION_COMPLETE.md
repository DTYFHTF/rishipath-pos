# Phase 7: Report Scheduling & Automation - Complete Implementation Guide

## Overview
Phase 7 implements a comprehensive automation system for scheduled report generation, intelligent alerts, and email notifications. The system includes automated report delivery, low stock monitoring, performance alerts, and a centralized automation dashboard.

## Features Implemented

### 1. Scheduled Reports
- **Automated Report Generation**: Generate and send reports on schedule (daily/weekly/monthly/custom)
- **Multiple Report Types**: Sales, Inventory, Customer Analytics, Cashier Performance
- **Flexible Distribution**: Email reports to multiple recipients with attachments
- **Format Options**: PDF and Excel support (text fallback when libraries not installed)
- **Execution Tracking**: Complete history of report runs with success/failure status

### 2. Alert System
- **Low Stock Alerts**: Notify when inventory falls below threshold
- **High Value Sale Alerts**: Monitor transactions above specified amount
- **Cashier Variance Alerts**: Detect cash discrepancies at session close
- **Inventory Discrepancy Alerts**: Track significant stock adjustments
- **Sales Target Alerts**: Celebrate when targets are achieved

### 3. Notification System
- **Multi-Severity Levels**: Info, Warning, Error, Critical
- **Email Delivery**: Automated email notifications with retry logic
- **Rich Templates**: Beautiful HTML email templates
- **Delivery Tracking**: Monitor sent/pending/failed notifications
- **Contextual Data**: Include detailed information in alerts

### 4. Automation Dashboard
- **Real-time Statistics**: Active schedules, success rates, critical alerts
- **Upcoming Schedules**: View next reports due to run
- **Recent Runs**: Monitor execution history
- **Alert Summary**: Breakdown by type and frequency
- **Recent Notifications**: Track latest alerts

## Database Schema

### report_schedules
```
- id: Primary key
- name: Schedule name
- report_type: sales, inventory, customer_analytics, cashier_performance
- frequency: daily, weekly, monthly, custom
- cron_expression: For custom schedules
- parameters: JSON (store_id, date_range, filters)
- recipients: JSON array of email addresses
- format: pdf, excel, both
- active: boolean
- last_run_at: timestamp
- next_run_at: timestamp
- created_by: FK to users
```

### scheduled_report_runs
```
- id: Primary key
- report_schedule_id: FK to report_schedules
- status: pending, running, completed, failed
- started_at: timestamp
- completed_at: timestamp
- error_message: text
- file_path: path to generated report
- file_size: in bytes
- records_processed: count
- metadata: JSON
```

### notifications
```
- id: Primary key
- type: notification type
- title: notification title
- message: notification message
- severity: info, warning, error, critical
- data: JSON (additional context)
- recipients: JSON array of recipients
- sent: boolean
- sent_at: timestamp
- send_error: text
- related_id: polymorphic ID
- related_type: polymorphic type
- triggered_by: FK to users
```

### alert_rules
```
- id: Primary key
- name: rule name
- type: alert type
- conditions: JSON (threshold, comparison)
- recipients: JSON array
- active: boolean
- frequency: immediate, hourly, daily
- last_triggered_at: timestamp
- trigger_count: integer
- store_id: FK to stores (optional)
- created_by: FK to users
```

## Core Services

### ReportScheduleService
Main service for automated report generation.

#### Key Methods:
```php
processDueSchedules(): int
// Process all due report schedules
// Returns: Number of schedules processed

generateAndSendReport(ReportSchedule $schedule): void
// Generate and send a specific schedule
// - Creates execution record
// - Generates report data
// - Saves files
// - Sends emails
// - Updates schedule timing

generateReportData(ReportSchedule $schedule): array
// Generate report data based on type
// Supports: sales, inventory, customer_analytics, cashier_performance
```

#### Report Types:
1. **Sales Report**: Total sales, transactions, average ticket
2. **Inventory Report**: Stock levels, low stock items, inventory value
3. **Customer Analytics**: Top customers, purchase patterns
4. **Cashier Performance**: Sales by cashier, transaction counts

### AlertService
Monitors system conditions and triggers notifications.

#### Key Methods:
```php
checkAllAlerts(): int
// Check all active alert rules
// Returns: Number of alerts fired

checkAlert(AlertRule $rule): bool
// Check a specific alert rule
// Returns: true if triggered

createNotification(...): Notification
// Create and send notification
// Handles email delivery and tracking
```

#### Alert Types:
1. **Low Stock**: Items below threshold
2. **High Value Sale**: Transactions above amount
3. **Cashier Variance**: Cash shortage/surplus
4. **Inventory Discrepancy**: Large stock adjustments
5. **Sales Target**: Goal achievement

## Console Commands

### reports:process-scheduled
Process all due scheduled reports.
```bash
php artisan reports:process-scheduled
```
**Scheduled**: Runs every hour
**Purpose**: Generate and send scheduled reports

### alerts:check
Check all active alert rules.
```bash
php artisan alerts:check
```
**Scheduled**: Runs every 15 minutes
**Purpose**: Monitor conditions and trigger alerts

### notifications:send-pending
Retry failed notification sends.
```bash
php artisan notifications:send-pending
```
**Scheduled**: Runs every hour
**Purpose**: Retry unsent notifications

## Laravel Scheduler Configuration

In `routes/console.php`:
```php
// Report Scheduling & Automation Tasks
Schedule::command('reports:process-scheduled')->hourly();
Schedule::command('alerts:check')->everyFifteenMinutes();
Schedule::command('notifications:send-pending')->hourly();
```

### Setting Up Cron
Add to crontab for production:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Development Testing
Run scheduler in foreground:
```bash
php artisan schedule:work
```

## Email Templates

### Scheduled Report Email
Location: `resources/views/emails/scheduled-report.blade.php`
- Gradient header with report title
- Summary statistics table
- Schedule information
- Attachment links

### Alert Notification Email
Location: `resources/views/emails/alert-notification.blade.php`
- Severity-based color coding
- Detailed data tables
- Alert type and severity badges
- Contextual information

### Report PDF Template
Location: `resources/views/reports/scheduled/template.blade.php`
- Clean layout for PDFs
- Summary tables
- Detailed data listings
- Professional formatting

## Model Scopes & Helpers

### ReportSchedule
```php
ReportSchedule::active()->get(); // Active schedules only
ReportSchedule::due()->get(); // Schedules due to run
$schedule->isDue(); // Check if due
$schedule->calculateNextRun(); // Calculate next run time
```

### AlertRule
```php
AlertRule::active()->get(); // Active rules
AlertRule::ofType('low_stock')->get(); // By type
$rule->shouldCheck(); // Check if should run based on frequency
$rule->markAsTriggered(); // Update trigger tracking
```

### Notification
```php
Notification::unsent()->get(); // Pending notifications
Notification::bySeverity('critical')->get(); // By severity
Notification::ofType('low_stock')->get(); // By type
$notification->markAsSent(); // Mark as sent
$notification->markAsFailed($error); // Mark as failed
```

## Admin Interface

### Automation Dashboard
Path: `/admin/automation-dashboard`
Features:
- 4 stat cards (schedules, alerts, success rate, critical alerts)
- Upcoming schedules list
- Recent report runs
- Alert summary breakdown
- Recent notifications feed

### Creating Report Schedules
1. Navigate to Automation Dashboard
2. Use Filament resources to create schedules
3. Configure:
   - Name and report type
   - Frequency (daily/weekly/monthly)
   - Parameters (date range, filters)
   - Recipients (email addresses)
   - Format (PDF/Excel/Both)

### Creating Alert Rules
1. Navigate to Alert Rules resource
2. Configure:
   - Name and type
   - Conditions (threshold, comparison)
   - Recipients
   - Frequency (immediate/hourly/daily)
   - Store filter (optional)

## Usage Examples

### Example 1: Daily Sales Report
```php
ReportSchedule::create([
    'name' => 'Daily Sales Summary',
    'report_type' => 'sales',
    'frequency' => 'daily',
    'parameters' => [
        'store_id' => 1,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->toDateString(),
    ],
    'recipients' => ['manager@store.com', 'owner@store.com'],
    'format' => 'pdf',
    'active' => true,
    'next_run_at' => now()->addDay()->hour(8)->minute(0),
]);
```

### Example 2: Low Stock Alert
```php
AlertRule::create([
    'name' => 'Low Stock Warning',
    'type' => 'low_stock',
    'conditions' => [
        'threshold' => 10,
        'comparison' => 'less_than_or_equal',
    ],
    'recipients' => ['inventory@store.com'],
    'active' => true,
    'frequency' => 'daily',
    'store_id' => 1,
]);
```

### Example 3: Manual Notification
```php
Notification::create([
    'type' => 'system_alert',
    'title' => 'System Maintenance Scheduled',
    'message' => 'System will be down for maintenance on...',
    'severity' => 'warning',
    'recipients' => ['all@store.com'],
    'data' => [
        'start_time' => '2026-01-15 02:00:00',
        'duration' => '2 hours',
    ],
]);
```

## Testing

### Test Script
Location: `test_automation.php`
Runs comprehensive tests:
- Schedule creation (daily/weekly/monthly)
- Alert rule configuration
- Notification system
- Frequency checking
- Due detection
- Next run calculations
- Model scopes

### Run Tests
```bash
php test_automation.php
```

### Manual Testing
```bash
# Test report processing
php artisan reports:process-scheduled

# Test alert checking
php artisan alerts:check

# Test notification sending
php artisan notifications:send-pending

# View logs
tail -f storage/logs/laravel.log
```

## Configuration

### Mail Setup
Update `.env` for email functionality:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourstore.com
MAIL_FROM_NAME="${APP_NAME}"
```

### PDF Generation
Install dompdf (optional):
```bash
composer require barryvdh/laravel-dompdf
```

### Excel Export
Install excel package (optional):
```bash
composer require maatwebsite/excel
```

## Performance Considerations

### Optimization Tips:
1. **Batch Processing**: Process schedules in chunks for large volumes
2. **Queue Jobs**: Use Laravel queues for report generation
3. **Cache Results**: Cache report data for repeated requests
4. **Index Database**: Ensure proper indexes on date/status columns
5. **Clean Old Data**: Archive old report runs periodically

### Recommended Indexes:
```sql
-- Already included in migrations
INDEX(report_schedule_id, created_at)
INDEX(type, sent, created_at)
INDEX(type, active)
```

## Troubleshooting

### Reports Not Running
1. Check cron is configured: `crontab -l`
2. Verify schedules are active: `ReportSchedule::active()->count()`
3. Check next_run_at times: `ReportSchedule::due()->get()`
4. Review logs: `storage/logs/laravel.log`

### Emails Not Sending
1. Verify mail configuration in `.env`
2. Check unsent notifications: `Notification::unsent()->count()`
3. Test mail: `php artisan tinker` then `Mail::raw('Test', fn($m) => $m->to('test@example.com')->subject('Test'))`
4. Review send errors in notifications table

### Alerts Not Triggering
1. Verify alert rules are active: `AlertRule::active()->count()`
2. Check frequency settings
3. Verify conditions match data
4. Test manually: `(new AlertService())->checkAlert($rule)`

## Future Enhancements

Potential additions for Phase 8+:
- SMS notifications via Twilio
- Slack/Teams integration
- Custom report templates
- Advanced scheduling (specific days/times)
- Report subscription management
- Dashboard widgets for reports
- API endpoints for external access
- Webhook notifications
- Report history comparison
- Scheduled data exports

## File Structure

```
app/
├── Console/Commands/
│   ├── ProcessScheduledReports.php
│   ├── CheckAlertRules.php
│   └── SendPendingNotifications.php
├── Filament/Pages/
│   └── AutomationDashboard.php
├── Models/
│   ├── ReportSchedule.php
│   ├── ScheduledReportRun.php
│   ├── Notification.php
│   └── AlertRule.php
└── Services/
    ├── ReportScheduleService.php
    └── AlertService.php

database/migrations/
├── *_create_report_schedules_table.php
├── *_create_scheduled_report_runs_table.php
├── *_create_notifications_table.php
└── *_create_alert_rules_table.php

resources/views/
├── emails/
│   ├── scheduled-report.blade.php
│   └── alert-notification.blade.php
├── filament/pages/
│   └── automation-dashboard.blade.php
└── reports/scheduled/
    └── template.blade.php

tests/
└── test_automation.php
```

## Summary

Phase 7 delivers a complete automation solution:
- ✅ 4 database tables with relationships
- ✅ 4 models with business logic
- ✅ 2 services (600+ lines combined)
- ✅ 3 console commands
- ✅ Laravel scheduler configured
- ✅ 3 email templates
- ✅ Automation dashboard
- ✅ Alert system with 5 types
- ✅ Comprehensive testing

The system is production-ready and extensible for future requirements.
