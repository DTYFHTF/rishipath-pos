# Organization Management Guide

## Overview

The Rishipath POS system supports multi-tenant organization management. Each organization can have multiple stores, users, products, and transactions. This guide explains how to manage organizations and work within the global organization context.

## Features

### 1. Organization CRUD Operations

**Navigation:** Admin Panel → Settings → Organizations

- **Create Organization:** Define organization details including name, slug, localization settings, and configuration
- **Edit Organization:** Update organization information
- **View Organization:** See organization details with related stores and users count
- **Delete Organization:** Remove organizations (with cascade rules applied to related records)

### 2. Global Organization Selector

**Location:** Top navigation bar (left side, before store selector)

- **Purpose:** Switch between organizations in multi-tenant environments
- **Access Control:**
  - Super admins see all active organizations
  - Regular users see only their assigned organization
- **Persistence:** Selected organization is stored in session
- **Event:** Broadcasts `organization-switched` event when changed

### 3. Organization Context Service

**Service Class:** `App\Services\OrganizationContext`

**Methods:**
- `getCurrentOrganizationId()` - Get current org ID from session
- `setCurrentOrganizationId($id)` - Set current org in session
- `getCurrentOrganization()` - Get current org model
- `getAccessibleOrganizations()` - Get orgs user can access
- `hasAccessToOrganization($id)` - Check access permission
- `initialize()` - Set default org on login
- `clear()` - Clear org context on logout

## Organization Schema

### Database Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | bigint | Primary key |
| `slug` | varchar(100) | Unique identifier (e.g., "main-org") |
| `name` | varchar(255) | Display name |
| `legal_name` | varchar(255) | Official registered name (nullable) |
| `country_code` | char(2) | ISO country code (e.g., "IN") |
| `currency` | char(3) | ISO currency code (e.g., "INR") |
| `timezone` | varchar(50) | Timezone (default: "Asia/Kolkata") |
| `locale` | varchar(5) | Locale code (default: "en") |
| `config` | json | Additional configuration (nullable) |
| `active` | boolean | Active status (default: true) |
| `created_at` | timestamp | Creation timestamp |
| `updated_at` | timestamp | Last update timestamp |

### Relationships

- **Stores:** `hasMany(Store::class)`
- **Users:** `hasMany(User::class)`
- **Products:** `hasMany(Product::class)`
- **Categories:** `hasMany(Category::class)`
- **Customers:** `hasMany(Customer::class)`
- **Suppliers:** `hasMany(Supplier::class)`
- **Sales:** `hasMany(Sale::class)`

## Usage Patterns

### In Controllers/Pages

```php
use App\Services\OrganizationContext;

// Get current organization ID
$orgId = OrganizationContext::getCurrentOrganizationId();

// Get current organization model
$org = OrganizationContext::getCurrentOrganization();

// Filter queries by organization
$products = Product::where('organization_id', $orgId)->get();
```

### In Livewire Components

```php
protected $listeners = ['organization-switched' => 'handleOrganizationSwitch'];

public function mount()
{
    $this->organizationId = OrganizationContext::getCurrentOrganizationId();
}

public function handleOrganizationSwitch($organizationId)
{
    $this->organizationId = $organizationId;
    // Reload data for new organization
    $this->loadData();
}
```

### In Resource Forms

```php
// Auto-set organization on create
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['organization_id'] = OrganizationContext::getCurrentOrganizationId();
    return $data;
}

// Filter options by organization
Select::make('category_id')
    ->options(fn () => Category::where('organization_id', OrganizationContext::getCurrentOrganizationId())
        ->pluck('name', 'id'))
```

## Multi-Tenant Best Practices

1. **Always scope queries** by `organization_id` when working with organization-scoped models
2. **Use the context service** instead of hardcoding organization IDs
3. **Listen to organization-switched event** in pages/components that display organization-specific data
4. **Validate organization access** before performing sensitive operations
5. **Set organization on record creation** automatically using form data mutation
6. **Filter dropdowns** by current organization to prevent cross-organization references

## Admin Workflows

### Initial Setup

1. **Create Organization:**
   - Go to Admin → Settings → Organizations → Create
   - Fill in organization details (name, slug, country, currency, timezone)
   - Set active status
   - Save

2. **Assign Users:**
   - Go to Admin → Users → Edit User
   - Select organization from dropdown
   - Assign stores within that organization
   - Save

3. **Switch Context:**
   - Use organization selector in topbar
   - Select target organization
   - All pages will reload with new organization context

### Daily Operations

- **Single Organization:** Selector shows current org name (no dropdown)
- **Multiple Organizations:** Selector shows dropdown to switch
- **Super Admin:** Can switch between all organizations
- **Regular User:** Limited to assigned organization

## Security Considerations

- **Access Control:** Users can only access organizations they're assigned to
- **Data Isolation:** Queries must be scoped by `organization_id` to prevent data leaks
- **Session Management:** Organization context is session-based and cleared on logout
- **Permissions:** Organization switching respects user roles and permissions

## Troubleshooting

### Organization selector not visible
- Check if user has multiple accessible organizations
- Verify `OrganizationContext::getAccessibleOrganizations()` returns results
- Clear cache: `php artisan cache:clear`

### Data not filtering by organization
- Ensure queries include `where('organization_id', OrganizationContext::getCurrentOrganizationId())`
- Check if component listens to `organization-switched` event
- Verify organization context is initialized on login

### Cannot create records in new organization
- Check if form mutation sets `organization_id` from context
- Verify user has permission to create in selected organization
- Ensure organization is active in database

## Future Enhancements

- [ ] Organization logo upload and branding
- [ ] Per-organization email/SMS templates
- [ ] Organization-level settings/preferences
- [ ] Cross-organization data transfer tools
- [ ] Organization analytics dashboard
- [ ] API keys per organization
