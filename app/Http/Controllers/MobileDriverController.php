<?php

namespace App\Http\Controllers;

use App\Models\ProofOfDelivery;
use App\Models\Trip;
use App\Models\TripCheckpoint;
use App\Models\TripConsignment;
use App\Models\TripExpense;
use App\Services\DocumentSequenceService;
use App\Services\GpsTrackingService;
use App\Services\TenantContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MobileDriverController extends Controller
{
    public function __construct(
        protected TenantContextService $tenantContext,
        protected GpsTrackingService $gpsService,
        protected DocumentSequenceService $sequenceService
    ) {}

    /**
     * Mobile Driver Dashboard: assigned trips.
     */
    public function dashboard(Request $request): View
    {
        $user = auth()->user();
        $companyId = $user->company_id;
        $driverId = $user->driver_id;

        // Query trips assigned to this driver via trip_resources or directly
        $tripsQuery = Trip::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['branch', 'resources.vehicle', 'consignments']);

        if ($driverId) {
            $tripsQuery->whereHas('resources', function ($q) use ($driverId) {
                $q->where('driver_id', $driverId);
            });
        }

        $activeTrips = (clone $tripsQuery)
            ->whereIn('trip_status', [Trip::STATUS_PLANNED, Trip::STATUS_LOADING, Trip::STATUS_DISPATCHED, Trip::STATUS_IN_TRANSIT])
            ->orderBy('trip_date', 'asc')
            ->get();

        $completedTrips = (clone $tripsQuery)
            ->whereIn('trip_status', [Trip::STATUS_OPERATIONALLY_COMPLETED, Trip::STATUS_CLOSED])
            ->latest('trip_date')
            ->limit(5)
            ->get();

        return view('mobile.driver.dashboard', compact('activeTrips', 'completedTrips', 'user'));
    }

    /**
     * Mobile Trip Detail View.
     */
    public function tripDetail(Trip $trip): View
    {
        $this->authorizeTripAccess($trip);

        $trip->load(['resources.vehicle', 'resources.driver', 'checkpoints', 'expenses', 'tripConsignments.consignment']);

        return view('mobile.driver.trip-detail', compact('trip'));
    }

    /**
     * Start Trip Execution.
     */
    public function startTrip(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorizeTripAccess($trip);

        $validated = $request->validate([
            'departure_odometer_km' => 'required|integer|min:0',
        ]);

        $trip->update([
            'trip_status' => Trip::STATUS_IN_TRANSIT,
            'departure_odometer_km' => $validated['departure_odometer_km'],
            'actual_departure' => now(),
        ]);

        return back()->with('success', "Trip {$trip->trip_number} started successfully.");
    }

    /**
     * Add En-Route Checkpoint.
     */
    public function addCheckpoint(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorizeTripAccess($trip);

        $validated = $request->validate([
            'location_name' => 'required|string|max:150',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $order = ($trip->checkpoints()->max('stop_order') ?? 0) + 1;

        TripCheckpoint::create([
            'company_id' => $trip->company_id,
            'trip_id' => $trip->id,
            'location_name' => $validated['location_name'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'stop_order' => $order,
            'arrival_time' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Checkpoint recorded.');
    }

    /**
     * Submit Driver En-Route Expense.
     */
    public function submitExpense(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorizeTripAccess($trip);

        $validated = $request->validate([
            'expense_category' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'receipt' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $documentPath = null;
        if ($request->hasFile('receipt')) {
            $documentPath = $request->file('receipt')->store('driver_expenses', 'public');
        }

        $voucherNumber = 'EXP-' . strtoupper(uniqid());

        TripExpense::create([
            'company_id' => $trip->company_id,
            'trip_id' => $trip->id,
            'voucher_number' => $voucherNumber,
            'vehicle_id' => $trip->primaryVehicle()?->id,
            'driver_id' => auth()->user()->driver_id,
            'expense_category' => $validated['expense_category'],
            'amount' => $validated['amount'],
            'expense_date' => now()->toDateString(),
            'payment_method' => $validated['payment_method'],
            'supporting_document_path' => $documentPath,
            'status' => TripExpense::STATUS_SUBMITTED,
        ]);

        return back()->with('success', 'Expense voucher submitted for review.');
    }

    /**
     * Delivery Confirmation & POD Upload.
     */
    public function confirmDelivery(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorizeTripAccess($trip);

        $validated = $request->validate([
            'trip_consignment_id' => 'required|exists:trip_consignments,id',
            'delivered_quantity' => 'required|integer|min:0',
            'damaged_quantity' => 'nullable|integer|min:0',
            'short_quantity' => 'nullable|integer|min:0',
            'receiver_name' => 'required|string|max:100',
            'receiver_phone' => 'nullable|string|max:30',
            'pod_document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $allocation = TripConsignment::findOrFail($validated['trip_consignment_id']);
        $allocation->update([
            'delivered_quantity' => $validated['delivered_quantity'],
            'damaged_quantity' => $validated['damaged_quantity'] ?? 0,
            'short_quantity' => $validated['short_quantity'] ?? 0,
            'delivery_status' => 'delivered_full',
            'delivery_time' => now(),
            'receiver_name' => $validated['receiver_name'],
            'receiver_phone' => $validated['receiver_phone'] ?? null,
        ]);

        if ($request->hasFile('pod_document')) {
            $filePath = $request->file('pod_document')->store('pods', 'public');
            ProofOfDelivery::create([
                'company_id' => $trip->company_id,
                'trip_id' => $trip->id,
                'trip_consignment_id' => $allocation->id,
                'file_path' => $filePath,
                'receiver_name' => $validated['receiver_name'],
                'receiver_phone' => $validated['receiver_phone'] ?? null,
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Delivery confirmation and POD recorded.');
    }

    /**
     * Operational Trip Completion.
     */
    public function completeTrip(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorizeTripAccess($trip);

        $validated = $request->validate([
            'arrival_odometer_km' => 'required|integer|min:' . (int)$trip->departure_odometer_km,
        ]);

        $distance = max(0, $validated['arrival_odometer_km'] - (int)$trip->departure_odometer_km);

        $trip->update([
            'trip_status' => Trip::STATUS_OPERATIONALLY_COMPLETED,
            'arrival_odometer_km' => $validated['arrival_odometer_km'],
            'total_distance_km' => $distance,
            'actual_arrival' => now(),
            'operationally_completed_at' => now(),
        ]);

        return redirect()->route('mobile.driver.dashboard')->with('success', "Trip {$trip->trip_number} completed!");
    }

    /**
     * Smartphone Geolocation Sync Endpoint.
     */
    public function recordLocation(Request $request): JsonResponse
    {
        $user = auth()->user();
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'trip_id' => 'nullable|exists:trips,id',
        ]);

        // Find active trip vehicle if available
        $trip = null;
        if (!empty($validated['trip_id'])) {
            $trip = Trip::withoutGlobalScopes()->find($validated['trip_id']);
        }

        $vehicleId = $trip?->primaryVehicle()?->id;

        if (!$vehicleId) {
            // Find any active vehicle assigned to this driver
            $vehicleId = \App\Models\TripResource::where('driver_id', $user->driver_id)
                ->where('is_active', true)
                ->value('vehicle_id');
        }

        if ($vehicleId) {
            $loc = $this->gpsService->recordLocation([
                'company_id' => $user->company_id,
                'vehicle_id' => $vehicleId,
                'trip_id' => $trip?->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'speed_kmh' => $validated['speed_kmh'] ?? 0,
                'heading_degrees' => $validated['heading'] ?? 0,
                'ignition_status' => true,
                'recorded_at' => now(),
            ]);

            return response()->json(['success' => true, 'location_id' => $loc->id]);
        }

        return response()->json(['success' => false, 'message' => 'No active vehicle assigned'], 422);
    }

    protected function authorizeTripAccess(Trip $trip): void
    {
        $user = auth()->user();
        if ($trip->company_id !== $user->company_id) {
            abort(403, 'Unauthorized tenant access.');
        }
    }
}

