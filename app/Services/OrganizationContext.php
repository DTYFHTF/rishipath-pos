<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

class OrganizationContext
{
    /**
     * Get the current organization ID from session
     */
    public static function getCurrentOrganizationId(): ?int
    {
        return session('current_organization_id');
    }

    /**
     * Set the current organization ID in session
     */
    public static function setCurrentOrganizationId(?int $organizationId): void
    {
        session(['current_organization_id' => $organizationId]);
    }

    /**
     * Get the current organization model
     */
    public static function getCurrentOrganization(): ?Organization
    {
        $organizationId = self::getCurrentOrganizationId();
        
        if (!$organizationId) {
            return null;
        }

        return Organization::where('active', true)->find($organizationId);
    }

    /**
     * Get all accessible organizations for the current user
     */
    public static function getAccessibleOrganizations()
    {
        $user = Auth::user();

        // Super admins see all organizations
        if ($user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return Organization::where('active', true)->orderBy('name')->get();
        }

        // Regular users see their assigned organization
        if ($user && $user->organization_id) {
            return Organization::where('id', $user->organization_id)
                ->where('active', true)
                ->get();
        }

        // Default: all active organizations
        return Organization::where('active', true)->orderBy('name')->get();
    }

    /**
     * Check if the current user has access to a specific organization
     */
    public static function hasAccessToOrganization(int $organizationId): bool
    {
        $accessibleOrgs = self::getAccessibleOrganizations();
        return $accessibleOrgs->contains('id', $organizationId);
    }

    /**
     * Initialize organization context (call this on login or session start)
     */
    public static function initialize(): void
    {
        $user = Auth::user();
        
        if (!$user) {
            return;
        }

        // If no organization is set in session, default to user's organization
        if (!self::getCurrentOrganizationId() && $user->organization_id) {
            self::setCurrentOrganizationId($user->organization_id);
        }

        // If still no organization, use the first accessible one
        if (!self::getCurrentOrganizationId()) {
            $firstOrg = self::getAccessibleOrganizations()->first();
            if ($firstOrg) {
                self::setCurrentOrganizationId($firstOrg->id);
            }
        }
    }

    /**
     * Clear organization context (call this on logout)
     */
    public static function clear(): void
    {
        session()->forget('current_organization_id');
    }
}
