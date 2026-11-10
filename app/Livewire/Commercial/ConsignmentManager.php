<?php

namespace App\Livewire\Commercial;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Consignment;
use App\Models\Customer;
use App\Services\ConsignmentService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class ConsignmentManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $customerFilter = null;

    // Create Consignment Modal
    public bool $showCreateModal = false;
    public ?int $booking_id = null;
    public string $consignment_date = '';
    public string $consignee_name = '';
    public string $consignee_phone = '';
    public string $consignee_cnic_or_ntn = '';
    public string $consignee_address = '';
    public string $consignee_city = 'Lahore';
    public string $pickup_point = 'Karachi';
    public string $delivery_point = 'Lahore';
    public string $cargo_description = '';
    public string $cargo_category = 'GENERAL_CARGO';
    public string $packaging_type = 'bags';
    public ?float $declared_value = null;
    public float $gross_weight_kg = 30000.00;
    public float $chargeable_weight_kg = 30000.00;
    public ?float $volume_cbm = 60.00;
    public int $total_quantity = 600;
    public float $freight_rate = 95000.00;
    public float $freight_charge = 95000.00;
    public float $handling_charge = 5000.00;
    public float $insurance_charge = 0.00;
    public string $payment_mode = 'to_pay';
    public string $delivery_instructions = '';

    // Split Dispatch Modal
    public bool $showSplitModal = false;
    public ?Consignment $splittingConsignment = null;
    public int $dispatch_quantity = 200;
    public string $dispatch_vehicle_reference = '';
    public string $dispatch_notes = '';

    // View Modal
    public bool $showDetailModal = false;
    public ?Consignment $viewingConsignment = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_consignments') && !$user->hasPermission('manage_consignments')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage consignments.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $consignments = Consignment::where('company_id', $companyId)
            ->with(['booking', 'customer', 'branch'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('consignment_number', 'like', '%' . $this->search . '%')
                      ->orWhere('consignee_name', 'like', '%' . $this->search . '%')
                      ->orWhere('consignee_city', 'like', '%' . $this->search . '%')
                      ->orWhere('cargo_description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->customerFilter, fn($q) => $q->where('customer_id', $this->customerFilter))
            ->latest()
            ->paginate(10);

        $bookings = Booking::where('company_id', $companyId)->where('status', '!=', Booking::STATUS_CANCELLED)->get();
        $customers = Customer::where('company_id', $companyId)->where('status', 'active')->get();

        return view('livewire.commercial.consignment-manager', [
            'consignments' => $consignments,
            'bookings' => $bookings,
            'customers' => $customers,
        ]);
    }

    public function updatedBookingId(?int $val): void
    {
        if ($val) {
            $booking = Booking::with('customer')->find($val);
            if ($booking) {
                $this->pickup_point = $booking->origin_city;
                $this->delivery_point = $booking->destination_city;
                $this->consignee_city = $booking->destination_city;
                $this->freight_charge = (float) $booking->total_freight;
                $this->handling_charge = (float) $booking->additional_charges;
                $this->total_quantity = $booking->total_packages_count;
                $this->gross_weight_kg = (float) $booking->total_weight_tons * 1000;
                $this->chargeable_weight_kg = $this->gross_weight_kg;
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $this->resetConsignmentForm();
        $this->showCreateModal = true;
    }

    public function resetConsignmentForm(): void
    {
        $this->booking_id = null;
        $this->consignment_date = now()->toDateString();
        $this->consignee_name = '';
        $this->consignee_phone = '';
        $this->consignee_cnic_or_ntn = '';
        $this->consignee_address = '';
        $this->consignee_city = 'Lahore';
        $this->pickup_point = 'Karachi';
        $this->delivery_point = 'Lahore';
        $this->cargo_description = '';
        $this->cargo_category = 'GENERAL_CARGO';
        $this->packaging_type = 'bags';
        $this->declared_value = null;
        $this->gross_weight_kg = 30000.00;
        $this->chargeable_weight_kg = 30000.00;
        $this->volume_cbm = 60.00;
        $this->total_quantity = 600;
        $this->freight_rate = 95000.00;
        $this->freight_charge = 95000.00;
        $this->handling_charge = 5000.00;
        $this->insurance_charge = 0.00;
        $this->payment_mode = 'to_pay';
        $this->delivery_instructions = '';
        $this->resetErrorBag();
    }

    public function saveConsignment(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'booking_id' => ['required', 'exists:bookings,id'],
            'consignee_name' => ['required', 'string', 'max:191'],
            'consignee_phone' => ['required', 'string', 'max:30'],
            'consignee_city' => ['required', 'string', 'max:100'],
            'cargo_description' => ['required', 'string', 'max:191'],
            'total_quantity' => ['required', 'integer', 'min:1'],
        ]);

        $booking = Booking::findOrFail($this->booking_id);
        app(TenantAuthorizationService::class)->validateTenantOwnership($booking);

        $consignment = app(ConsignmentService::class)->createConsignment($booking, [
            'consignment_date' => $this->consignment_date,
            'consignee_name' => $this->consignee_name,
            'consignee_phone' => $this->consignee_phone,
            'consignee_cnic_or_ntn' => $this->consignee_cnic_or_ntn,
            'consignee_address' => $this->consignee_address,
            'consignee_city' => $this->consignee_city,
            'pickup_point' => $this->pickup_point,
            'delivery_point' => $this->delivery_point,
            'cargo_description' => $this->cargo_description,
            'cargo_category' => $this->cargo_category,
            'packaging_type' => $this->packaging_type,
            'declared_value' => $this->declared_value,
            'gross_weight_kg' => $this->gross_weight_kg,
            'chargeable_weight_kg' => $this->chargeable_weight_kg,
            'volume_cbm' => $this->volume_cbm,
            'total_quantity' => $this->total_quantity,
            'freight_rate' => $this->freight_rate,
            'freight_charge' => $this->freight_charge,
            'handling_charge' => $this->handling_charge,
            'insurance_charge' => $this->insurance_charge,
            'payment_mode' => $this->payment_mode,
            'delivery_instructions' => $this->delivery_instructions,
        ]);

        session()->flash('success', sprintf('Consignment Note [%s] issued.', $consignment->consignment_number));
        $this->showCreateModal = false;
        $this->resetConsignmentForm();
    }

    public function openSplitModal(int $consignmentId): void
    {
        $this->checkAuthorization();
        /** @var Consignment $consignment */
        $consignment = Consignment::withoutGlobalScopes()->findOrFail($consignmentId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($consignment);

        if ($consignment->remaining_quantity <= 0) {
            session()->flash('error', 'All packages for this consignment have already been dispatched.');
            return;
        }

        $this->splittingConsignment = $consignment;
        $this->dispatch_quantity = min(200, $consignment->remaining_quantity);
        $this->dispatch_vehicle_reference = '';
        $this->dispatch_notes = '';
        $this->showSplitModal = true;
    }

    public function splitAndDispatch(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'dispatch_quantity' => ['required', 'integer', 'min:1', 'max:' . $this->splittingConsignment->remaining_quantity],
        ]);

        app(ConsignmentService::class)->splitAndDispatch(
            $this->splittingConsignment,
            $this->dispatch_quantity,
            [
                'vehicle_ref' => $this->dispatch_vehicle_reference,
                'notes' => $this->dispatch_notes,
            ]
        );

        session()->flash('success', sprintf('Successfully dispatched [%d] packages on Consignment [%s]. Remaining: [%d].', $this->dispatch_quantity, $this->splittingConsignment->consignment_number, $this->splittingConsignment->remaining_quantity));
        $this->showSplitModal = false;
    }

    public function viewConsignment(int $consignmentId): void
    {
        $this->checkAuthorization();
        $this->viewingConsignment = Consignment::withoutGlobalScopes()
            ->with(['booking', 'customer', 'branch', 'items', 'creator'])
            ->findOrFail($consignmentId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingConsignment);

        $this->showDetailModal = true;
    }
}

