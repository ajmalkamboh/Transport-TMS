<?php

namespace App\Http\Controllers;

use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\ProofOfDelivery;
use App\Models\VehicleLocation;
use App\Services\TenantContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomerPortalController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Customer Portal Dashboard.
     */
    public function dashboard(Request $request): View
    {
        $user = auth()->user();
        $customer = $this->resolveCustomer();

        $companyId = $user->company_id;
        $customerId = $customer->id;

        $activeConsignmentsCount = Consignment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('delivery_status', [Consignment::STATUS_BOOKED, Consignment::STATUS_DISPATCHED, Consignment::STATUS_IN_TRANSIT])
            ->count();

        $deliveredCount = Consignment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('delivery_status', [Consignment::STATUS_DELIVERED, 'delivered_full'])
            ->count();

        $unpaidInvoicesCount = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('balance_due', '>', 0)
            ->count();

        $totalBalanceDue = (float) CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->sum('balance_due');

        $recentConsignments = Consignment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->with(['branch'])
            ->latest('id')
            ->limit(5)
            ->get();

        $recentInvoices = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->latest('invoice_date')
            ->limit(5)
            ->get();

        return view('portal.customer.dashboard', compact(
            'customer',
            'activeConsignmentsCount',
            'deliveredCount',
            'unpaidInvoicesCount',
            'totalBalanceDue',
            'recentConsignments',
            'recentInvoices'
        ));
    }

    /**
     * Consignment Tracking List.
     */
    public function consignments(Request $request): View
    {
        $customer = $this->resolveCustomer();
        $query = Consignment::withoutGlobalScopes()
            ->where('company_id', auth()->user()->company_id)
            ->where('customer_id', $customer->id)
            ->with(['branch', 'trips.resources.vehicle']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('consignment_number', 'like', "%{$search}%")
                  ->orWhere('origin_city', 'like', "%{$search}%")
                  ->orWhere('destination_city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('delivery_status', $request->input('status'));
        }

        $consignments = $query->latest('id')->paginate(15);

        return view('portal.customer.consignments', compact('customer', 'consignments'));
    }

    /**
     * Live Consignment Tracking & POD View.
     */
    public function consignmentDetail(Consignment $consignment): View
    {
        $this->authorizeCustomerAccess($consignment);

        $consignment->load(['trips.checkpoints', 'trips.resources.vehicle']);

        // Check for latest vehicle location if in-transit
        $activeTrip = $consignment->trips()->whereIn('trip_status', ['in_transit', 'dispatched'])->first();
        $latestLocation = null;

        if ($activeTrip) {
            $vehicleId = $activeTrip->primaryVehicle()?->id;
            if ($vehicleId) {
                $latestLocation = VehicleLocation::withoutGlobalScopes()
                    ->where('vehicle_id', $vehicleId)
                    ->latest('recorded_at')
                    ->first();
            }
        }

        // Get PODs
        $pods = ProofOfDelivery::withoutGlobalScopes()
            ->where('company_id', $consignment->company_id)
            ->whereIn('trip_id', $consignment->trips->pluck('id'))
            ->get();

        return view('portal.customer.consignment-detail', compact('consignment', 'activeTrip', 'latestLocation', 'pods'));
    }

    /**
     * Customer Invoices & Ledger.
     */
    public function invoices(Request $request): View
    {
        $customer = $this->resolveCustomer();
        $query = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', auth()->user()->company_id)
            ->where('customer_id', $customer->id);

        if ($request->filled('status')) {
            if ($request->input('status') === 'unpaid') {
                $query->where('balance_due', '>', 0);
            } elseif ($request->input('status') === 'paid') {
                $query->where('balance_due', '<=', 0);
            }
        }

        $invoices = $query->latest('invoice_date')->paginate(15);
        $totalBalance = (float) (clone $query)->sum('balance_due');

        return view('portal.customer.invoices', compact('customer', 'invoices', 'totalBalance'));
    }

    /**
     * Download Proof of Delivery Document.
     */
    public function downloadPod(ProofOfDelivery $pod): BinaryFileResponse|RedirectResponse
    {
        $user = auth()->user();
        if ($pod->company_id !== $user->company_id) {
            abort(403, 'Unauthorized company scope.');
        }

        // Validate that this POD belongs to one of customer's consignments
        $belongsToCustomer = Consignment::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('customer_id', $user->customer_id)
            ->whereHas('trips', function ($q) use ($pod) {
                $q->where('trips.id', $pod->trip_id);
            })->exists();

        if (!$belongsToCustomer) {
            abort(403, 'Access denied: POD does not belong to your account.');
        }

        $fullPath = storage_path('app/public/' . $pod->file_path);
        if (file_exists($fullPath)) {
            return response()->download($fullPath);
        }

        return back()->with('error', 'POD document file not found on server.');
    }

    protected function resolveCustomer(): Customer
    {
        $user = auth()->user();
        if (!$user->customer_id) {
            abort(403, 'Access restricted to customer portal accounts.');
        }

        $customer = Customer::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->find($user->customer_id);

        if (!$customer) {
            abort(404, 'Associated customer account not found.');
        }

        return $customer;
    }

    protected function authorizeCustomerAccess(Consignment $consignment): void
    {
        $user = auth()->user();
        if ($consignment->company_id !== $user->company_id || $consignment->customer_id !== $user->customer_id) {
            abort(403, 'Unauthorized access to consignment.');
        }
    }
}
