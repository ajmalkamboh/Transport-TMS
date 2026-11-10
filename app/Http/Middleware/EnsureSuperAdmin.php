<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Restrict route access exclusively to Platform Super Admins.
     * Tenant owners and business managers MUST NOT receive platform admin access.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (!$user->isSuperAdmin()) {
            throw new AuthorizationException('Access denied: This area requires Platform Super Administrator privileges.');
        }

        return $next($request);
    }
}

