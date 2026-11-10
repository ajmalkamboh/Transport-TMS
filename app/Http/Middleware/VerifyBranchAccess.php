<?php

namespace App\Http\Middleware;

use App\Services\BranchAccessService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBranchAccess
{
    public function __construct(
        protected BranchAccessService $branchAccessService
    ) {}

    /**
     * Handle incoming request and verify branch authorization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if branch ID is present in route parameters or query
        $branchId = $request->route('branch') ?? $request->route('branch_id') ?? $request->input('branch_id');

        if ($branchId !== null) {
            $branchId = (int) (is_object($branchId) ? $branchId->id : $branchId);

            if (!$this->branchAccessService->userHasBranchAccess($user, $branchId)) {
                throw new AuthorizationException('You are not authorized to view or perform operations for this branch.');
            }
        }

        return $next($request);
    }
}

