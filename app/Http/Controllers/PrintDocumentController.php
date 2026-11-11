<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Consignment;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\Quotation;
use App\Models\VehicleOwner;
use App\Services\OwnerLedgerService;
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

    public function fuelSlip(int $id)
    {
        $fuelEntry = \App\Models\FuelEntry::withoutGlobalScopes()
            ->with(['company', 'branch', 'supplier', 'station', 'card', 'vehicle', 'driver', 'trip', 'creator'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($fuelEntry);

        return view('print.fuel-slip', compact('fuelEntry'));
    }

    public function expenseVoucher(int $id)
    {
        $expense = \App\Models\TripExpense::withoutGlobalScopes()
            ->with(['company', 'branch', 'trip', 'vehicle', 'driver', 'recorder', 'approver', 'poster'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($expense);

        return view('print.expense-voucher', compact('expense'));
    }

    public function jobCard(int $id)
    {
        $jobCard = \App\Models\JobCard::withoutGlobalScopes()
            ->with(['company', 'branch', 'vehicle', 'driver', 'items', 'creator', 'completer'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($jobCard);

        return view('print.job-card', compact('jobCard'));
    }

    public function ownerSettlement(int $id)
    {
        $settlement = OwnerSettlement::withoutGlobalScopes()
            ->with(['company', 'branch', 'owner', 'vehicle', 'trip', 'contract', 'deductionItems', 'payments', 'preparedByUser', 'approvedByUser'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($settlement);

        return view('print.owner-settlement', compact('settlement'));
    }

    public function ownerPayment(int $id)
    {
        $payment = OwnerPayment::withoutGlobalScopes()
            ->with(['company', 'branch', 'owner', 'settlement', 'createdByUser'])
            ->findOrFail($id);
        $this->tenantAuth->validateTenantOwnership($payment);

        return view('print.owner-payment', compact('payment'));
    }

    public function ownerLedger(Request $request, int $ownerId)
    {
        $owner = VehicleOwner::withoutGlobalScopes()->with(['company', 'vehicles'])->findOrFail($ownerId);
        $this->tenantAuth->validateTenantOwnership($owner);

        $fromDate = $request->query('from');
        $toDate = $request->query('to');

        $ledgerSummary = app(OwnerLedgerService::class)->getOwnerLedgerSummary($owner, $fromDate, $toDate);

        return view('print.owner-ledger', compact('owner', 'ledgerSummary', 'fromDate', 'toDate'));
    }
}

