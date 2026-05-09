<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalesAgent;

class SalesAgentCommissionService
{
    public static function calculateForSale(Sale $sale, SalesAgent $agent): array
    {
        $total = (float) ($sale->total_amount ?? 0);
        $base = (float) ($sale->wholesale_base_amount ?? 0);
        $channel = $sale->order_channel ?? 'retail';

        if ($channel === 'wholesale') {
            $isEligibleWholesale = $total >= (float) $agent->min_wholesale_amount;
            if (! $isEligibleWholesale) {
                return [
                    'eligible' => false,
                    'reason' => 'Wholesale threshold not met',
                    'company_profit' => max(0, $total - $base),
                    'commission' => 0.0,
                ];
            }

            $companyProfit = max(0, $total - $base);
            $commission = round($companyProfit * ((float) $agent->commission_wholesale_profit_pct / 100), 2);

            return [
                'eligible' => true,
                'reason' => null,
                'company_profit' => $companyProfit,
                'commission' => $commission,
            ];
        }

        $companyProfit = max(0, $total - $base);
        $commission = round($companyProfit * ((float) $agent->commission_retail_pct / 100), 2);

        return [
            'eligible' => true,
            'reason' => null,
            'company_profit' => $companyProfit,
            'commission' => $commission,
        ];
    }
}
