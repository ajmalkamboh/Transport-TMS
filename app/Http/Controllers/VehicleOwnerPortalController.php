<?php

namespace App\Http\Controllers;

use App\Models\OwnerLedger;
use App\Models\OwnerSettlement;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VehicleOwnerPortalController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Vehicle Owner Dashboard.
     */
    public function dashboard(Request $request): View
    {
        $user = auth()->user();
        $owner = $this->resolveOwner();
        $companyId = $user->company_id;
        $ownerId = $owner->id;

        $attachedVehiclesCount = Vehicle::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('vehicle_owner_id', $ownerId)
            ->count();

        $activeTripsCount = Trip::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('trip_status', [Trip::STATUS_LOADING, Trip::STATUS_DISPATCHED, Trip::STATUS_IN_TRANSIT])
            ->whereHas('resources', function ($q) use ($ownerId) {
                $q->whereHas('vehicle', function ($vq) use ($ownerId) {
                    $vq->where('vehicle_owner_id', $ownerId);
                });
            })
            ->count();

        $unsettledSettlementsCount = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('vehicle_owner_id', $ownerId)
            ->where('balance_due', '>', 0)
            ->count();

        $totalPayableBalance = (float) OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('vehicle_owner_id', $ownerId)
            ->sum('balance_due');

        $recentSettlements = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('vehicle_owner_id', $ownerId)
            ->with('vehicle')
            ->latest('settlement_date')
            ->limit(5)
            ->get();

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('vehicle_owner_id', $ownerId)
            ->get();

        return view('portal.owner.dashboard', compact(
            'owner',
            'attachedVehiclesCount',
            'activeTripsCount',
            'unsettledSettlementsCount',
            'totalPayableBalance',
            'recentSettlements',
            'vehicles'
        ));
    }

    /**
     * Attached Vehicles List.
     */
    public function vehicles(Request $request): View
    {
        $owner = $this->resolveOwner();

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('company_id', auth()->user()->company_id)
            ->where('vehicle_owner_id', $owner->id)
            ->latest('id')
            ->paginate(15);

        return view('portal.owner.vehicles', compact('owner', 'vehicles'));
    }

    /**
     * Owner Trip History & Freight Shares.
     */
    public function trips(Request $request): View
    {
        $owner = $this->resolveOwner();
        $companyId = auth()->user()->company_id;
        $ownerId = $owner->id;

        $trips = Trip::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereHas('resources', function ($q) use ($ownerId) {
                $q->whereHas('vehicle', function ($vq) use ($ownerId) {
                    $vq->where('vehicle_owner_id', $ownerId);
                });
            })
            ->with(['resources.vehicle', 'latestOwnerSettlement'])
            ->latest('trip_date')
            ->paginate(15);

        return view('portal.owner.trips', compact('owner', 'trips'));
    }

    /**
     * Owner Settlements & Payables.
     */
    public function settlements(Request $request): View
    {
        $owner = $this->resolveOwner();
        $query = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', auth()->user()->company_id)
            ->where('vehicle_owner_id', $owner->id)
            ->with('vehicle');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $settlements = $query->latest('settlement_date')->paginate(15);
        $totalBalanceDue = (float) (clone $query)->sum('balance_due');

        return view('portal.owner.settlements', compact('owner', 'settlements', 'totalBalanceDue'));
    }

    /**
     * Settlement Detail Breakdown.
     */
    public function settlementDetail(OwnerSettlement $settlement): View
    {
        $this->authorizeOwnerAccess($settlement);

        $settlement->load(['vehicle', 'trip', 'deductions', 'contract']);

        return view('portal.owner.settlement-detail', compact('settlement'));
    }

    protected function resolveOwner(): VehicleOwner
    {
        $user = auth()->user();
        if (!$user->vehicle_owner_id) {
            abort(403, 'Access restricted to vehicle owner portal accounts.');
        }

        $owner = VehicleOwner::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->find($user->vehicle_owner_id);

        if (!$owner) {
            abort(404, 'Associated vehicle owner account not found.');
        }

        return $owner;
    }

    protected function authorizeOwnerAccess(OwnerSettlement $settlement): void
    {
        $user = auth()->user();
        if ($settlement->company_id !== $user->company_id || $settlement->vehicle_owner_id !== $user->vehicle_owner_id) {
            abort(403, 'Unauthorized access to owner settlement.');
        }
    }
}

