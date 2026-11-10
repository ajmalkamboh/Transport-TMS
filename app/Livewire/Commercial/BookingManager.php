<?php

namespace App\Livewire\Commercial;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Services\BookingService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class BookingManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $customerFilter = null;

    // Create Modal
    public bool $showCreateModal = false;
    public ?int $customer_id = null;
    public ?int $customer_contract_id = null;
    public ?int $branch_id = null;
    public string $booking_date = '';
    public string $origin_city = 'Karachi';
    public string $destination_city = 'Lahore';
    public string $origin_address = '';
    public string $destination_address = '';
    public ?string $expected_dispatch_date = null;
    public ?string $expected_delivery_date = null;
    public string $payment_terms = Booking::TERMS_TO_PAY;

    // Cargo & Consignee
    public string $cargo_description = '';
    public string $cargo_category = 'GENERAL_CARGO';
    public string $packaging_type = 'bags';
    public float $weight_tons = 30.00;
    public int $package_count = 600;
    public string $consignee_name = '';
    public string $consignee_phone = '';
    public string $consignee_cnic_or_ntn = '';

    // Pricing & Advance
    public float $total_freight = 95000.00;
    public float $additional_charges = 5000.00;
    public float $discount_amount = 0.00;
    public float $tax_amount = 0.00;
    public float $advance_received = 0.00;
    public string $advance_payment_method = 'cash';
    public string $advance_receipt_reference = '';
    public string $special_instructions = '';

    // Record Advance Modal
    public bool $showAdvanceModal = false;
    public ?Booking $advancingBooking = null;
    public float $new_advance_amount = 10000.00;
    public string $new_advance_method = 'cash';
    public string $new_advance_ref = '';

    // Cancel Booking Modal
    public bool $showCancelModal = false;
    public ?Booking $cancellingBooking = null;
    public string $cancellation_reason = '';

    // View Modal
    public bool $showDetailModal = false;
    public ?Booking $viewingBooking = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_bookings') && !$user->hasPermission('manage_bookings')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage cargo bookings.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $bookings = Booking::where('company_id', $companyId)
            ->with(['customer', 'branch', 'quotation', 'consignments'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('booking_number', 'like', '%' . $this->search . '%')
                      ->orWhere('origin_city', 'like', '%' . $this->search . '%')
                      ->orWhere('destination_city', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->customerFilter, fn($q) => $q->where('customer_id', $this->customerFilter))
            ->latest()
            ->paginate(10);

        $customers = Customer::where('company_id', $companyId)->where('status', 'active')->get();
        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->get();
        $contracts = $this->customer_id
            ? CustomerContract::where('company_id', $companyId)->where('customer_id', $this->customer_id)->where('status', 'active')->get()
            : collect();

        return view('livewire.commercial.booking-manager', [
            'bookings' => $bookings,
            'customers' => $customers,
            'branches' => $branches,
            'contracts' => $contracts,
        ]);
    }

    public function updatedCustomerId(?int $val): void
    {
        if ($val) {
            $customer = Customer::find($val);
            if ($customer) {
                $this->consignee_name = $customer->company_name ?: $customer->name;
                $this->consignee_phone = $customer->phone;
                $this->consignee_cnic_or_ntn = $customer->tax_ntn ?? '';
                $this->payment_terms = $customer->payment_terms === 'paid' ? Booking::TERMS_PAID : Booking::TERMS_TO_PAY;

                $activeContract = $customer->activeContract;
                if ($activeContract) {
                    $this->customer_contract_id = $activeContract->id;
                    $this->total_freight = (float) $activeContract->base_rate;
                }
            }
        }
    }

    public function openCreateBookingModal(): void
    {
        $this->checkAuthorization();
        $this->resetBookingForm();
        $this->showCreateModal = true;
    }

    public function resetBookingForm(): void
    {
        $this->customer_id = null;
        $this->customer_contract_id = null;
        $this->branch_id = null;
        $this->booking_date = now()->toDateString();
        $this->origin_city = 'Karachi';
        $this->destination_city = 'Lahore';
        $this->origin_address = '';
        $this->destination_address = '';
        $this->expected_dispatch_date = now()->addDay()->toDateString();
        $this->expected_delivery_date = now()->addDays(3)->toDateString();
        $this->payment_terms = Booking::TERMS_TO_PAY;
        $this->cargo_description = '';
        $this->cargo_category = 'GENERAL_CARGO';
        $this->packaging_type = 'bags';
        $this->weight_tons = 30.00;
        $this->package_count = 600;
        $this->consignee_name = '';
        $this->consignee_phone = '';
        $this->consignee_cnic_or_ntn = '';
        $this->total_freight = 95000.00;
        $this->additional_charges = 5000.00;
        $this->discount_amount = 0.00;
        $this->tax_amount = 0.00;
        $this->advance_received = 0.00;
        $this->advance_payment_method = 'cash';
        $this->advance_receipt_reference = '';
        $this->special_instructions = '';
        $this->resetErrorBag();
    }

    public function saveBooking(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = \App\Models\Company::findOrFail($companyId);

        $rules = [
            'customer_id' => ['required', 'exists:customers,id'],
            'origin_city' => ['required', 'string', 'max:100'],
            'destination_city' => ['required', 'string', 'max:100'],
            'cargo_description' => ['required', 'string', 'max:191'],
            'weight_tons' => ['required', 'numeric', 'min:0'],
            'package_count' => ['required', 'integer', 'min:1'],
            'total_freight' => ['required', 'numeric', 'min:0'],
            'consignee_name' => ['required', 'string', 'max:191'],
            'consignee_phone' => ['required', 'string', 'max:30'],
        ];

        $this->validate($rules);

        $booking = app(BookingService::class)->createBooking($company, [
            'customer_id' => $this->customer_id,
            'customer_contract_id' => $this->customer_contract_id,
            'branch_id' => $this->branch_id,
            'booking_date' => $this->booking_date,
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,
            'origin_address' => $this->origin_address,
            'destination_address' => $this->destination_address,
            'expected_dispatch_date' => $this->expected_dispatch_date,
            'expected_delivery_date' => $this->expected_delivery_date,
            'payment_terms' => $this->payment_terms,
            'cargo_description' => $this->cargo_description,
            'cargo_category' => $this->cargo_category,
            'packaging_type' => $this->packaging_type,
            'weight_tons' => $this->weight_tons,
            'package_count' => $this->package_count,
            'consignee_name' => $this->consignee_name,
            'consignee_phone' => $this->consignee_phone,
            'consignee_cnic_or_ntn' => $this->consignee_cnic_or_ntn,
            'total_freight' => $this->total_freight,
            'additional_charges' => $this->additional_charges,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'advance_received' => $this->advance_received,
            'advance_payment_method' => $this->advance_payment_method,
            'advance_receipt_reference' => $this->advance_receipt_reference,
            'special_instructions' => $this->special_instructions,
        ]);

        session()->flash('success', sprintf('Booking [%s] registered with initial Consignment Note issued.', $booking->booking_number));
        $this->showCreateModal = false;
        $this->resetBookingForm();
    }

    public function openRecordAdvanceModal(int $bookingId): void
    {
        $this->checkAuthorization();
        /** @var Booking $booking */
        $booking = Booking::withoutGlobalScopes()->findOrFail($bookingId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($booking);

        $this->advancingBooking = $booking;
        $this->new_advance_amount = min(20000.00, (float) $booking->balance_amount);
        $this->new_advance_method = 'cash';
        $this->new_advance_ref = '';
        $this->showAdvanceModal = true;
    }

    public function recordAdvance(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'new_advance_amount' => ['required', 'numeric', 'min:1'],
            'new_advance_method' => ['required', 'string'],
        ]);

        app(BookingService::class)->recordAdvance(
            $this->advancingBooking,
            $this->new_advance_amount,
            $this->new_advance_method,
            $this->new_advance_ref
        );

        session()->flash('success', sprintf('Customer advance of PKR %s recorded for Booking [%s].', number_format($this->new_advance_amount, 2), $this->advancingBooking->booking_number));
        $this->showAdvanceModal = false;
    }

    public function openCancelModal(int $bookingId): void
    {
        $this->checkAuthorization();
        /** @var Booking $booking */
        $booking = Booking::withoutGlobalScopes()->findOrFail($bookingId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($booking);

        if (!$booking->canBeCancelled()) {
            session()->flash('error', 'Cannot cancel booking: active dispatches or completed deliveries already exist.');
            return;
        }

        $this->cancellingBooking = $booking;
        $this->cancellation_reason = 'Customer order cancelled prior to dispatch.';
        $this->showCancelModal = true;
    }

    public function cancelBooking(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        app(BookingService::class)->cancelBooking($this->cancellingBooking, $this->cancellation_reason);

        session()->flash('success', sprintf('Booking [%s] cancelled successfully.', $this->cancellingBooking->booking_number));
        $this->showCancelModal = false;
    }

    public function viewBooking(int $bookingId): void
    {
        $this->checkAuthorization();
        $this->viewingBooking = Booking::withoutGlobalScopes()
            ->with(['customer', 'branch', 'quotation', 'consignments.items', 'creator'])
            ->findOrFail($bookingId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingBooking);

        $this->showDetailModal = true;
    }
}
