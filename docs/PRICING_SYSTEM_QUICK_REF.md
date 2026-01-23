# Pricing System Quick Reference

## Problem
POS showed `selling_price_nepal` in search but `mrp_india` in cart - inconsistent pricing across the application.

## Solution
Created `PricingService` that automatically selects correct price field and currency based on Organization's country.

## Quick Usage

### Get Price
```php
use App\Services\PricingService;

$price = PricingService::getSellingPrice($variant, $organization);
```

### Format with Currency
```php
$formatted = PricingService::formatPrice($price, $organization);
// India: ₹1,234.56
// Nepal: रू1,234.56
```

### In Blade
```blade
@php
    $price = \App\Services\PricingService::getSellingPrice($variant, auth()->user()?->organization);
    $currency = \App\Services\PricingService::getCurrencySymbol(auth()->user()?->organization);
@endphp
{{ $currency }}{{ number_format($price, 2) }}
```

Or use Blade directives (shorter):
```blade
@price($variant->getSellingPrice(auth()->user()?->organization))
@currency {{ number_format($price, 2) }}
```

## Price Field Selection

| Country | Code | Price Field | Currency |
|---------|------|-------------|----------|
| India | IN | `mrp_india` | ₹ (INR) |
| Nepal | NP | `selling_price_nepal` | रू (NPR) |
| Other | * | `base_price` | Custom |

## Tax Rates

| Country | Tax Type | Rate |
|---------|----------|------|
| India | GST | 12% |
| Nepal | VAT | 13% |

## Files Changed

**New:**
- `app/Services/PricingService.php`

**Updated:**
- `app/Filament/Pages/EnhancedPOS.php`
- `app/Filament/Pages/InventoryList.php`
- `app/Filament/Pages/ProfitReport.php`
- `resources/views/filament/pages/inventory-list.blade.php`
- `resources/views/filament/pages/product-detail-modal.blade.php`
- `resources/views/filament/components/barcode-display.blade.php`

## Testing

1. Check POS product search displays correct prices
2. Add items to cart - verify correct prices used
3. View inventory list - check currency symbols
4. Print barcode - verify price shown
5. View profit reports - ensure calculations correct

Full documentation: [PRICING_SERVICE_IMPLEMENTATION.md](PRICING_SERVICE_IMPLEMENTATION.md)
