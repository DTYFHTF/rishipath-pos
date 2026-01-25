<?php

namespace App\Http\Middleware;

use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class InitializeOrganizationContext
{
    /**
     * Handle an incoming request and initialize organization context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Initialize organization context if user is authenticated
        if (Auth::check()) {
            OrganizationContext::initialize();
            
            // Debug logging to verify middleware is running
            \Log::info('OrganizationContext Middleware', [
                'session_org_id' => session('current_organization_id'),
                'user_org_id' => Auth::user()->organization_id,
                'user_id' => Auth::id(),
            ]);
        }

        return $next($request);
    }
}
