<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaasController extends Controller
{
    /**
     * Display Platform SaaS governance dashboard.
     */
    public function index(Request $request): View
    {
        $companies = Company::withCount(['branches', 'users'])->latest()->get();
        $totalCompanies = $companies->count();
        $activeCompanies = $companies->where('status', 'active')->count();
        $totalBranches = Branch::withoutGlobalScopes()->count();
        $totalUsers = User::withoutGlobalScopes()->where('is_super_admin', false)->count();

        return view('saas.dashboard', [
            'companies' => $companies,
            'totalCompanies' => $totalCompanies,
            'activeCompanies' => $activeCompanies,
            'totalBranches' => $totalBranches,
            'totalUsers' => $totalUsers,
        ]);
    }
}

