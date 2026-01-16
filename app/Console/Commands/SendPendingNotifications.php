<?php

namespace App\Console\Commands;

use App\Services\AlertService;
use Illuminate\Console\Command;

class SendPendingNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send any pending notifications that failed previously';

    /**
     * Execute the console command.
     */
    public function handle(AlertService $service)
    {
        $this->info('Sending pending notifications...');

        $processed = $service->processUnsentNotifications();

        $this->info("✅ Processed {$processed} pending notifications");

        return Command::SUCCESS;
    }
}
