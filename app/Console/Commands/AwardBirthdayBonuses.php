<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\LoyaltyService;
use Illuminate\Console\Command;

class AwardBirthdayBonuses extends Command
{
    protected $signature = 'loyalty:birthday-bonuses';

    protected $description = 'Award birthday bonus points to customers whose birthday is today';

    public function handle(LoyaltyService $loyaltyService): int
    {
        $this->info('Checking for birthday bonuses...');

        $customers = Customer::whereNotNull('birthday')
            ->whereNotNull('loyalty_enrolled_at')
            ->whereRaw('strftime("%m-%d", birthday) = ?', [now()->format('m-d')])
            ->where(function ($q) {
                $q->whereNull('last_birthday_bonus_at')
                    ->orWhereYear('last_birthday_bonus_at', '<', now()->year);
            })
            ->get();

        $count = 0;

        foreach ($customers as $customer) {
            if ($customer->isBirthdayBonusDue()) {
                $loyaltyService->awardBirthdayBonus($customer);
                $this->info("🎂 Birthday bonus awarded to: {$customer->name}");
                $count++;
            }
        }

        $this->info("✅ Awarded birthday bonuses to {$count} customers");

        return Command::SUCCESS;
    }
}
