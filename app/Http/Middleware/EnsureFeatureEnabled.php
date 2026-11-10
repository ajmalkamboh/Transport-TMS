<?php

namespace App\Http\Middleware;

use App\Services\FeatureManagerService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        protected FeatureManagerService $featureManager
    ) {}

    /**
     * Handle an incoming request and enforce server-side feature access.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Platform Super Admin has access to all features
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (!$user->company_id) {
            throw new AuthorizationException('No company associated with user account.');
        }

        if (!$this->featureManager->hasFeature($user->company_id, $feature)) {
            throw new AuthorizationException(
                sprintf('Access Denied: The feature [%s] is not included in your transport company subscription plan.', $feature)
            );
        }

        return $next($request);
    }
}
