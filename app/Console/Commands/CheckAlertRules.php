<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AlertService;

class CheckAlertRules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all active alert rules and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(AlertService $service)
    {
        $this->info('Checking alert rules...');
        
        $alertsFired = $service->checkAllAlerts();
        
        if ($alertsFired > 0) {
            $this->warn("⚠️  {$alertsFired} alerts triggered");
        } else {
            $this->info('✅ No alerts triggered');
        }
        
        return Command::SUCCESS;
    }
}
