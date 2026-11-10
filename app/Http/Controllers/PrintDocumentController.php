<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Consignment;
use App\Models\Quotation;
use App\Services\TenantAuthorizationService;
use Illuminate\Http\Request;

class PrintDocumentController extends Controller
{
    public function __construct(
        protected TenantAuthorizationService $tenantAuth
    ) {}

    public function quotation(int $id)
    {
        $quotation = Quotation::withoutGlobalScopes()->with(['company', 'customer', 'branch'])->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($quotation);

        return view('print.quotation', compact('quotation'));
    }

    public function booking(int $id)
    {
        $booking = Booking::withoutGlobalScopes()->with(['company', 'customer', 'branch', 'consignments'])->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($booking);

        return view('print.booking', compact('booking'));
    }

    public function consignment(int $id)
    {
        $consignment = Consignment::withoutGlobalScopes()->with(['company', 'customer', 'branch', 'booking'])->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($consignment);

        return view('print.consignment-note', compact('consignment'));
    }

    public function cargoReceipt(int $id)
    {
        $consignment = Consignment::withoutGlobalScopes()->with(['company', 'customer', 'branch', 'booking'])->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($consignment);

        return view('print.cargo-receipt', compact('consignment'));
    }

    public function bookingSummary(int $id)
    {
        $booking = Booking::withoutGlobalScopes()->with(['company', 'customer', 'branch', 'consignments'])->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($booking);

        return view('print.booking-summary', compact('booking'));
    }

    public function loadingSlip(int $id)
    {
        $trip = \App\Models\Trip::withoutGlobalScopes()
            ->with(['company', 'branch', 'dispatcher', 'tripConsignments.consignment.customer', 'resources.vehicle', 'resources.driver'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($trip);

        return view('print.loading-slip', compact('trip'));
    }

    public function tripSheet(int $id)
    {
        $trip = \App\Models\Trip::withoutGlobalScopes()
            ->with(['company', 'branch', 'dispatcher', 'dispatchedByUser', 'tripConsignments.consignment.customer', 'resources.vehicle', 'resources.driver', 'checkpoints'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($trip);

        return view('print.trip-sheet', compact('trip'));
    }

    public function podReceipt(int $id)
    {
        $pod = \App\Models\ProofOfDelivery::withoutGlobalScopes()
            ->with(['company', 'trip', 'consignment.customer', 'tripConsignment', 'verifier'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($pod);

        return view('print.pod-receipt', compact('pod'));
    }
}

