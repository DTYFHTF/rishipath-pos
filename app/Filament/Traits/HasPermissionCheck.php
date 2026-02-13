<?php

namespace App\Filament\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait HasPermissionCheck
{
    /**
     * Check if current user can access this resource
     */
    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super admins have full access
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check specific permission
        $permission = static::getPermissionName('view');

        return $user->hasPermission($permission);
    }

    /**
     * Check if user can create records
     */
    public static function canCreate(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::getPermissionName('create');

        return $user->hasPermission($permission);
    }

    /**
     * Check if user can edit a record
     */
    public static function canEdit($record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::getPermissionName('edit');

        return $user->hasPermission($permission);
    }

    /**
     * Check if user can delete a record
     */
    public static function canDelete($record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::getPermissionName('delete');

        return $user->hasPermission($permission);
    }

    /**
     * Get permission name for the resource action
     */
    protected static function getPermissionName(string $action): string
    {
        $modelName = class_basename(static::getModel());
        $resourceName = strtolower(str_replace('_', '', Str::snake($modelName)));

        // Map resource names to permission names
        $permissionMap = [
            'product' => 'products',
            'productvariant' => 'product_variants',
            'category' => 'categories',
            'customer' => 'customers',
            'sale' => 'sales',
            'productbatch' => 'product_batches',
            'supplier' => 'suppliers',
            'store' => 'stores',
            'role' => 'roles',
            'user' => 'users',
            'retailstore' => 'retail_stores',
            'bulkorderinquiry' => 'bulk_order_inquiries',
            'invoice' => 'invoices',
        ];

        $permissionResource = $permissionMap[$resourceName] ?? $resourceName;

        return "{$action}_{$permissionResource}";
    }
}
