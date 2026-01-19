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
        // First check session
        $storeId = Session::get('current_store_id');
        
        if (!$storeId && Auth::check()) {
            // Fall back to user's first assigned store
            $userStores = Auth::user()->stores ?? [];
            if (!empty($userStores)) {
                $storeId = $userStores[0];
                self::setCurrentStoreId($storeId);
            } else {
                // Fall back to first active store
                $firstStore = Store::where('active', true)->first();
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
        
        // Super admin sees all stores
        if ($user->hasRole('super-admin')) {
            return Store::where('active', true)->get();
        }

        // Get user's assigned stores
        $userStoreIds = $user->stores ?? [];
        
        if (empty($userStoreIds)) {
            return Store::where('active', true)->get();
        }

        return Store::where('active', true)
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
