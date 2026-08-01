<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ProductVariant;

class PricingService
{
    /**
     * Margin applied over cost for wholesale (retail-store) billing.
     *
     * This is the same 13% used to print the dealer price list, so a wholesale
     * bill raised in the POS always matches the sheet the shop was handed.
     */
    public const WHOLESALE_MARKUP = 1.13;

    public static function suggestVariantPrices(?float $costPrice, ?float $packSize, ?string $unit): array
    {
        if ($costPrice === null || $packSize === null || blank($unit)) {
            return [
                'markup_percent' => null,
                'base_price' => null,
                'mrp_india' => null,
                'selling_price_nepal' => null,
            ];
        }

        $markupPercent = self::suggestMarkupPercent($packSize, $unit);
        $rawPrice = $costPrice * (1 + ($markupPercent / 100));
        $retailPrice = (float) (ceil($rawPrice / 5) * 5);

        return [
            'markup_percent' => $markupPercent,
            'base_price' => $retailPrice,
            'mrp_india' => $retailPrice,
            'selling_price_nepal' => $retailPrice,
        ];
    }

    public static function suggestMarkupPercent(?float $packSize, ?string $unit): float
    {
        $normalizedSize = self::normalizeComparableSize($packSize, $unit);

        if ($normalizedSize === null) {
            return 35.0;
        }

        // Small packs (< 50 g, e.g. 20 g) previously carried a 90% markup which
        // made them disproportionately expensive. Align them with the 50 g rate
        // so a 20 g pack earns the same margin as a 50 g pack.
        if ($normalizedSize <= 50.0) {
            return 65.0;
        }

        if (abs($normalizedSize - 100.0) < 0.001 || abs($normalizedSize - 200.0) < 0.001) {
            return 50.0;
        }

        if (abs($normalizedSize - 500.0) < 0.001) {
            return 30.0;
        }

        if (abs($normalizedSize - 1000.0) < 0.001) {
            return 25.0;
        }

        if ($normalizedSize < 100) {
            return 70.0;
        }

        if ($normalizedSize < 500) {
            return 40.0;
        }

        if ($normalizedSize < 1000) {
            return 28.0;
        }

        return 20.0;
    }

    public static function getPricingRuleSummary(?float $packSize, ?string $unit): string
    {
        if ($packSize === null || blank($unit)) {
            return 'Enter pack size, unit, and cost price to auto-suggest MRP and selling price.';
        }

        $markupPercent = self::suggestMarkupPercent($packSize, $unit);
        $unitLabel = strtoupper((string) $unit);
        $displaySize = floor((float) $packSize) == (float) $packSize
            ? (string) (int) $packSize
            : number_format((float) $packSize, 3);

        return "Auto-pricing rule: {$markupPercent}% markup for {$displaySize} {$unitLabel}. You can still override the suggested values.";
    }

    protected static function normalizeComparableSize(?float $packSize, ?string $unit): ?float
    {
        if ($packSize === null || blank($unit)) {
            return null;
        }

        $normalizedUnit = strtoupper(trim((string) $unit));

        return match ($normalizedUnit) {
            'G', 'GM', 'GMS', 'GRAM', 'GRAMS', 'ML' => $packSize,
            'KG', 'KGS', 'KILOGRAM', 'KILOGRAMS', 'L' => $packSize * 1000,
            default => null,
        };
    }

    /**
     * Get the selling price for a product variant based on organization country
     */
    public static function getSellingPrice(ProductVariant $variant, ?Organization $organization = null): float
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            // Fallback if no organization context
            return $variant->selling_price_nepal ?? $variant->base_price ?? 0;
        }

        return match ($organization->country_code) {
            'IN' => $variant->mrp_india ?? $variant->base_price ?? 0,
            'NP' => $variant->selling_price_nepal ?? $variant->base_price ?? 0,
            default => $variant->base_price ?? 0,
        };
    }

    /**
     * Get the price field name based on organization country
     */
    public static function getPriceFieldName(?Organization $organization = null): string
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            return 'selling_price_nepal'; // Default fallback
        }

        return match ($organization->country_code) {
            'IN' => 'mrp_india',
            'NP' => 'selling_price_nepal',
            default => 'base_price',
        };
    }

    /**
     * Get the currency symbol based on organization
     */
    public static function getCurrencySymbol(?Organization $organization = null): string
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            return '₹'; // Default fallback
        }

        // Use organization's currency field if set, otherwise derive from country
        if ($organization->currency) {
            return match ($organization->currency) {
                'INR' => '₹',
                'NPR' => 'रू',
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                default => $organization->currency.' ',
            };
        }

        return match ($organization->country_code) {
            'IN' => '₹',
            'NP' => 'रू',
            default => '₹',
        };
    }

    /**
     * Get the currency code based on organization
     */
    public static function getCurrencyCode(?Organization $organization = null): string
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            return 'NPR'; // Default fallback
        }

        // Use organization's currency field if set, otherwise derive from country
        if ($organization->currency) {
            return $organization->currency;
        }

        return match ($organization->country_code) {
            'IN' => 'INR',
            'NP' => 'NPR',
            default => 'NPR',
        };
    }

    /**
     * Format price with currency symbol
     */
    public static function formatPrice(float $price, ?Organization $organization = null, int $decimals = 2): string
    {
        $symbol = self::getCurrencySymbol($organization);

        return $symbol.number_format($price, $decimals);
    }

    /**
     * Get the current organization from session/context
     */
    protected static function getCurrentOrganization(): ?Organization
    {
        // Try to get from authenticated user
        $user = auth()->user();
        if ($user && $user->organization_id) {
            return Organization::find($user->organization_id);
        }

        // Try to get from session
        $orgId = session('current_organization_id');
        if ($orgId) {
            return Organization::find($orgId);
        }

        // Fallback to first active organization
        return Organization::where('active', true)->first();
    }

    /**
     * Get store-specific pricing if available, otherwise use organization pricing
     */
    public static function getStorePricing(ProductVariant $variant, int $storeId, ?Organization $organization = null): float
    {
        // Check for store-specific custom pricing first
        $storePricing = $variant->storePricing()->where('store_id', $storeId)->first();

        if ($storePricing && $storePricing->custom_price) {
            return $storePricing->custom_price;
        }

        // Fall back to organization-based pricing
        return self::getSellingPrice($variant, $organization);
    }

    /**
     * Dealer price for a variant — cost plus the standard wholesale margin,
     * rounded up to a whole rupee so bills stay cash-friendly.
     *
     * Returns null when the variant has no cost price: guessing a wholesale
     * rate from an unknown cost would silently sell below cost.
     */
    public static function getWholesalePrice(ProductVariant $variant): ?float
    {
        $cost = (float) ($variant->cost_price ?? 0);

        if ($cost <= 0) {
            return null;
        }

        return (float) ceil($cost * self::WHOLESALE_MARKUP);
    }

    /**
     * Unit price for a POS line, honouring the wholesale toggle.
     *
     * Falls back to retail pricing when wholesale is requested but the variant
     * has no cost on record, so the cashier is never handed a zero-rupee line.
     */
    public static function getPosPrice(
        ProductVariant $variant,
        int $storeId,
        ?Organization $organization = null,
        bool $wholesale = false,
    ): float {
        if ($wholesale && ($wholesalePrice = self::getWholesalePrice($variant)) !== null) {
            return $wholesalePrice;
        }

        return self::getStorePricing($variant, $storeId, $organization);
    }

    /**
     * Get tax rate based on organization country
     */
    public static function getTaxRate(?Organization $organization = null): float
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            return 0.0;
        }

        $salesBillType = data_get($organization->config, 'tax.sales_bill_type');
        if ($salesBillType === 'PAN') {
            return 0.0;
        }

        return match ($organization->country_code) {
            'IN' => 12.0,
            'NP' => 13.0,
            default => 0.0,
        };
    }

    /**
     * Get tax label based on organization config/country.
     */
    public static function getTaxLabel(?Organization $organization = null): string
    {
        $organization = $organization ?? self::getCurrentOrganization();

        if (! $organization) {
            return 'Tax';
        }

        $configured = data_get($organization->config, 'tax.sales_bill_type');
        if (is_string($configured) && $configured !== '') {
            return strtoupper($configured);
        }

        return match ($organization->country_code) {
            'IN' => 'GST',
            'NP' => 'VAT',
            default => 'Tax',
        };
    }
}
