<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\ProductVariant;

class PricingService
{
    /**
     * Get the selling price for a product variant based on organization country
     */
    public static function getSellingPrice(ProductVariant $variant, ?Organization $organization = null): float
    {
        $organization = $organization ?? self::getCurrentOrganization();
        
        if (!$organization) {
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
        
        if (!$organization) {
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
        
        if (!$organization) {
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
                default => $organization->currency . ' ',
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
        
        if (!$organization) {
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
        return $symbol . number_format($price, $decimals);
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
     * Get tax rate based on organization country
     */
    public static function getTaxRate(?Organization $organization = null): float
    {
        $organization = $organization ?? self::getCurrentOrganization();
        
        if (!$organization) {
            return 13.0; // Default VAT for Nepal
        }

        return match ($organization->country_code) {
            'IN' => 12.0, // GST
            'NP' => 13.0, // VAT
            default => 0.0,
        };
    }

    /**
     * Get tax label based on organization country
     */
    public static function getTaxLabel(?Organization $organization = null): string
    {
        $organization = $organization ?? self::getCurrentOrganization();
        
        if (!$organization) {
            return 'VAT';
        }

        return match ($organization->country_code) {
            'IN' => 'GST',
            'NP' => 'VAT',
            default => 'Tax',
        };
    }
}
