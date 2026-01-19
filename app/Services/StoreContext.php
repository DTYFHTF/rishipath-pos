<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class StoreContext
{
    /**
     * Get the current store ID from session or user default
     */
    public static function getCurrentStoreId(): ?int
    {
        $currentOrgId = OrganizationContext::getCurrentOrganizationId();
        
        // If no organization context, can't determine store
        if (!$currentOrgId || !Auth::check()) {
            return null;
        }
        
        // Check session for stored store ID
        $storeId = Session::get('current_store_id');
        
        // Validate that the stored storeId belongs to current organization
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store && $store->organization_id == $currentOrgId && $store->active) {
                return $storeId;
            }
            // Clear invalid store from session
            self::clearCurrentStore();
            $storeId = null;
        }
        
        // If no valid store in session, find one for current organization
        if (!$storeId) {
            // Fall back to user's first assigned store (within current organization)
            $userStores = Auth::user()->stores ?? [];
            if (!empty($userStores)) {
                // Find first store that belongs to current organization
                $validStore = Store::whereIn('id', $userStores)
                    ->where('organization_id', $currentOrgId)
                    ->where('active', true)
                    ->first();
                    
                if ($validStore) {
                    $storeId = $validStore->id;
                    self::setCurrentStoreId($storeId);
                }
            }
            
            // If still no store, fall back to first active store in current organization
            if (!$storeId) {
                $firstStore = Store::where('organization_id', $currentOrgId)
                    ->where('active', true)
                    ->first();
                if ($firstStore) {
                    $storeId = $firstStore->id;
                    self::setCurrentStoreId($storeId);
                }
            }
        }
        
        return $storeId;
    }

    /**
     * Set the current store ID in session
     */
    public static function setCurrentStoreId(int $storeId): void
    {
        Session::put('current_store_id', $storeId);
    }

    /**
     * Clear the current store from session
     */
    public static function clearCurrentStore(): void
    {
        Session::forget('current_store_id');
    }

    /**
     * Get the current Store model
     */
    public static function getCurrentStore(): ?Store
    {
        $storeId = self::getCurrentStoreId();
        return $storeId ? Store::find($storeId) : null;
    }

    /**
     * Get all stores accessible to the current user
     */
    public static function getAccessibleStores()
    {
        if (!Auth::check()) {
            return collect();
        }

        $user = Auth::user();
        $currentOrgId = OrganizationContext::getCurrentOrganizationId();
        
        // If no organization context, return empty
        if (!$currentOrgId) {
            return collect();
        }
        
        // Super admin sees all stores for current organization
        if ($user->hasRole('super-admin')) {
            return Store::where('active', true)
                ->where('organization_id', $currentOrgId)
                ->get();
        }

        // Get user's assigned stores for current organization
        $userStoreIds = $user->stores ?? [];
        
        if (empty($userStoreIds)) {
            return Store::where('active', true)
                ->where('organization_id', $currentOrgId)
                ->get();
        }

        return Store::where('active', true)
            ->where('organization_id', $currentOrgId)
            ->whereIn('id', $userStoreIds)
            ->get();
    }

    /**
     * Check if user has access to a specific store
     */
    public static function hasAccessToStore(int $storeId): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();
        
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $userStoreIds = $user->stores ?? [];
        return in_array($storeId, $userStoreIds);
    }
}
