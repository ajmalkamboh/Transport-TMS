<?php

namespace App\Http\Controllers;

use App\Services\AuditLogService;
use App\Services\BranchAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __construct(
        protected BranchAccessService $branchAccessService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Switch active branch session.
     */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $user = auth()->user();
        $this->branchAccessService->switchActiveBranch($user, (int) $validated['branch_id']);

        $this->auditLogService->log(
            'branch_switched',
            $user,
            null,
            ['active_branch_id' => $validated['branch_id']],
            sprintf('User switched active branch to ID [%s].', $validated['branch_id'])
        );

        return back()->with('status', 'Active branch switched successfully.');
    }
}

