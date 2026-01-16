<?php

namespace App\Console\Commands;

use App\Services\ReportScheduleService;
use Illuminate\Console\Command;

class ProcessScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process all due scheduled reports and send them via email';

    /**
     * Execute the console command.
     */
    public function handle(ReportScheduleService $service)
    {
        $this->info('Processing scheduled reports...');

        $processedCount = $service->processDueSchedules();

        $this->info("✅ Processed {$processedCount} scheduled reports");

        return Command::SUCCESS;
    }
}
