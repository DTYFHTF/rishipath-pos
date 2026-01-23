# Multi-Currency Pricing System

**Date:** 2025-01-24  
**Feature:** Organization-based currency and pricing system

## Overview

Implemented a centralized pricing system that automatically selects the correct price field and currency based on the organization's country. This resolves inconsistencies where different parts of the system (POS, inventory, reports) were using different price fields.

## Problem Solved

### Before
- **POS product search** used `selling_price_nepal`
- **POS cart** used `mrp_india`  
- **Inventory list** used `selling_price_nepal`
- **Reports** had mixed logic with multiple fallbacks
- **Currency symbol** was hardcoded to ₹ everywhere

### After
- All pricing logic centralized in `PricingService`
- Automatic selection based on `Organization.country_code`
- Consistent currency symbols
- Dynamic tax rates (GST for India, VAT for Nepal)

## Architecture

### PricingService

Located: `app/Services/PricingService.php`

**Key Methods:**

#### `getSellingPrice(ProductVariant $variant, ?Organization $organization): float`
Returns the appropriate selling price based on organization country:
- **India (IN)**: Uses `mrp_india`
- **Nepal (NP)**: Uses `selling_price_nepal`
- **Others**: Uses `base_price`
- Fallback: `base_price` if specific field is null

#### `getStorePricing(ProductVariant $variant, int $storeId, ?Organization $organization): float`
Priority order:
1. Store-specific custom pricing (if set)
2. Organization-based pricing (via `getSellingPrice`)

#### `getCurrencySymbol(?Organization $organization): string`
Returns currency symbol:
- **INR**: ₹
- **NPR**: रू
- **USD**: $
- **EUR**: €
- **GBP**: £

#### `getCurrencyCode(?Organization $organization): string`
Returns ISO currency code (INR, NPR, USD, etc.)

#### `formatPrice(float $price, ?Organization $organization, int $decimals = 2): string`
Formats price with currency symbol: `₹1,234.56` or `रू1,234.56`

#### `getTaxRate(?Organization $organization): float`
Returns applicable tax rate:
- **India**: 12% (GST)
- **Nepal**: 13% (VAT)

#### `getTaxLabel(?Organization $organization): string`
Returns tax label:
- **India**: "GST"
- **Nepal**: "VAT"

### Organization Model

**Relevant Fields:**
```php
'country_code'  // ISO country code: 'IN', 'NP', etc.
'currency'      // Optional: 'INR', 'NPR', 'USD', etc.
'timezone'      // For localized datetime
'locale'        // For localized formatting
```

### ProductVariant Price Fields

```php
'base_price'            // Base/cost-plus price (fallback)
'mrp_india'            // Maximum Retail Price for India (includes GST)
'selling_price_nepal'  // Selling price for Nepal (excludes VAT)
'cost_price'           // Purchase/cost price
```

## Updated Components

### Backend (PHP)

#### ✅ **EnhancedPOS.php**
- Product search pricing: Uses `PricingService::getStorePricing()`
- Cart pricing: Uses `PricingService::getStorePricing()`
- Tax rates: Uses `PricingService::getTaxRate()`

#### ✅ **InventoryList.php**
- Metrics calculation: Uses `PricingService::getPriceFieldName()`
- Sale value computation based on correct price field

#### ✅ **ProfitReport.php**
- Category analysis: Uses `PricingService::getSellingPrice()`
- Product analysis: Uses `PricingService::getSellingPrice()`

### Frontend (Blade Views)

#### ✅ **inventory-list.blade.php**
```blade
@php
    $organization = auth()->user()?->organization;
    $price = \App\Services\PricingService::getSellingPrice($variant, $organization);
    $currency = \App\Services\PricingService::getCurrencySymbol($organization);
@endphp
{{ $currency }}{{ number_format($price, 2) }}
```

#### ✅ **product-detail-modal.blade.php**
- Base Price column: Uses `PricingService::getSellingPrice()`
- MRP column: Uses calculated MRP or fetches from appropriate field
- Currency symbols: Dynamic based on organization

#### ✅ **barcode-display.blade.php**
- Price display: Uses `PricingService::getSellingPrice()`
- Currency: Dynamic symbol

## Usage Examples

### In Controllers/Livewire Components

```php
use App\Services\PricingService;

// Get selling price
$price = PricingService::getSellingPrice($variant, $organization);

// Get store-specific price (includes custom pricing)
$storePrice = PricingService::getStorePricing($variant, $storeId, $organization);

// Format with currency
$formatted = PricingService::formatPrice($price, $organization);
// Output: "₹1,234.56" or "रू1,234.56"

// Get tax rate
$taxRate = PricingService::getTaxRate($organization);
// Output: 12.0 (India) or 13.0 (Nepal)
```

### In Blade Templates

```blade
@php
    $org = auth()->user()?->organization;
    $price = \App\Services\PricingService::getSellingPrice($variant, $org);
    $currency = \App\Services\PricingService::getCurrencySymbol($org);
@endphp

<div class="price">
    {{ $currency }}{{ number_format($price, 2) }}
</div>
```

### In Query Builders

```php
use App\Services\PricingService;

$organization = auth()->user()->organization;
$priceField = PricingService::getPriceFieldName($organization);

$totalValue = ProductVariant::query()
    ->selectRaw("SUM(quantity * COALESCE({$priceField}, base_price, 0)) as total")
    ->value('total');
```

## Organization Context Resolution

The service automatically resolves organization context in this order:

1. **Passed parameter**: `PricingService::getSellingPrice($variant, $specificOrg)`
2. **Authenticated user**: `auth()->user()->organization`
3. **Session**: `session('current_organization_id')`
4. **First active org**: `Organization::where('active', true)->first()`

## Benefits

### 1. **Consistency**
- Single source of truth for pricing logic
- No more hardcoded price fields scattered across codebase
- Reduced maintenance burden

### 2. **Flexibility**
- Easy to add new countries/currencies
- Store-specific pricing still supported
- Configurable per organization

### 3. **Localization**
- Correct currency symbols
- Proper tax rates and labels
- Region-appropriate pricing

### 4. **Scalability**
- Centralized logic easy to extend
- Support for multiple currencies
- Future-proof for multi-market expansion

## Migration Notes

### Existing Data
- No database changes required
- All existing price fields remain intact
- System selects appropriate field at runtime

### Configuration
Organizations should have:
```php
'country_code' => 'IN' or 'NP'  // Required
'currency' => 'INR' or 'NPR'    // Optional (derived from country_code if not set)
```

### Testing Checklist
- ✅ POS product search shows correct prices
- ✅ POS cart uses correct prices for organization
- ✅ Inventory list displays correct currency and prices
- ✅ Product details modal shows appropriate pricing
- ✅ Reports calculate using correct price fields
- ✅ Barcodes print with correct prices
- ✅ Store-specific pricing overrides work
- ✅ Tax rates apply correctly

## Future Enhancements

### Potential Additions
1. **Exchange Rate Support**: Auto-convert between currencies
2. **Price History**: Track price changes over time
3. **Multi-Currency Display**: Show prices in multiple currencies simultaneously
4. **Dynamic Pricing Rules**: Time-based, volume-based pricing
5. **Regional Promotions**: Country-specific discounts

### Additional Currency Support
To add a new currency:
1. Add country code case in `PricingService::getSellingPrice()`
2. Add currency symbol in `PricingService::getCurrencySymbol()`
3. Add tax rate in `PricingService::getTaxRate()`
4. Optionally: Add new price field to `product_variants` table

## Related Files

### Core Service
- `app/Services/PricingService.php` - Main pricing logic

### Updated Controllers
- `app/Filament/Pages/EnhancedPOS.php`
- `app/Filament/Pages/InventoryList.php`
- `app/Filament/Pages/ProfitReport.php`

### Updated Views
- `resources/views/filament/pages/inventory-list.blade.php`
- `resources/views/filament/pages/product-detail-modal.blade.php`
- `resources/views/filament/components/barcode-display.blade.php`

### Models
- `app/Models/Organization.php` - Country/currency config
- `app/Models/ProductVariant.php` - Price fields

## Support

For questions or issues:
1. Check `PricingService` source for method documentation
2. Verify organization `country_code` is set correctly
3. Ensure price fields are populated in `product_variants`
4. Review logs for pricing-related errors
