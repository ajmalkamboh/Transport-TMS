<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaasController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Display the Platform Super Admin Dashboard with accurate live metrics.
     */
    public function index(Request $request): View
    {
        // 1. Tenant Overview Metrics
        $totalCompanies = Company::count();
        $activeTenants = Company::where('status', 'active')->count();
        $suspendedTenants = Company::where('status', 'suspended')->count();
        $trialTenants = Company::where('status', 'trial')->count();

        // 2. Subscription Metrics
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '>', now())
            ->count();

        $expiredSubscriptions = Subscription::where(function ($q) {
            $q->where('status', 'expired')
              ->orWhere(function ($sub) {
                  $sub->where('ends_at', '<=', now())
                      ->whereNotIn('status', ['cancelled']);
              });
        })->count();

        // 3. SaaS Revenue & Payment Metrics
        $mrr = $this->subscriptionService->calculateMRR();
        $totalRevenue = $this->subscriptionService->calculateTotalRevenue();
        $pendingPayments = $this->subscriptionService->getPendingPaymentsCount();
        $pendingInvoicesAmount = (float) SubscriptionInvoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance_amount');

        // 4. Recent Data for Feed
        $recentTenants = Company::with(['plan', 'owner'])
            ->latest()
            ->take(6)
            ->get();

        $recentPayments = SubscriptionPayment::with(['company', 'invoice'])
            ->latest()
            ->take(6)
            ->get();

        return view('saas.dashboard', [
            'totalCompanies' => $totalCompanies,
            'activeTenants' => $activeTenants,
            'suspendedTenants' => $suspendedTenants,
            'trialTenants' => $trialTenants,
            'activeSubscriptions' => $activeSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'mrr' => $mrr,
            'totalRevenue' => $totalRevenue,
            'pendingPayments' => $pendingPayments,
            'pendingInvoicesAmount' => $pendingInvoicesAmount,
            'recentTenants' => $recentTenants,
            'recentPayments' => $recentPayments,
        ]);
    }

    /**
     * Display Tenant Companies Management view.
     */
    public function tenants(): View
    {
        return view('saas.tenants.index');
    }

    /**
     * Display Subscription Plans Management view.
     */
    public function plans(): View
    {
        return view('saas.plans.index');
    }

    /**
     * Display SaaS Billing & Payments Management view.
     */
    public function billing(): View
    {
        return view('saas.billing.index');
    }
}
