<?php

namespace App\Livewire\Commercial;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\Quotation;
use App\Services\FreightCalculationService;
use App\Services\QuotationService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class QuotationManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $customerFilter = null;

    // Modal state
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $selectedQuotationId = null;

    // Form fields
    public ?int $customer_id = null;
    public ?int $customer_contract_id = null;
    public ?int $branch_id = null;
    public string $quotation_date = '';
    public string $valid_until = '';
    public string $origin_city = 'Karachi';
    public string $destination_city = 'Lahore';
    public string $origin_address = '';
    public string $destination_address = '';
    public string $cargo_description = '';
    public string $cargo_category = 'GENERAL_CARGO';
    public float $weight_tons = 30.00;
    public ?float $volume_cbm = 60.00;
    public int $package_count = 600;
    public string $required_vehicle_category = 'HCV';
    public string $required_vehicle_type = 'TRAILER_22W';
    public string $rate_type = 'fixed';
    public float $base_rate = 95000.00;
    public ?float $distance_km = 1250.00;
    public float $loading_charges = 2500.00;
    public float $unloading_charges = 2500.00;
    public float $toll_charges = 4500.00;
    public float $detention_charges = 0.00;
    public float $other_charges = 0.00;
    public float $discount_percentage = 0.00;
    public float $tax_percentage = 16.00;
    public string $terms_and_conditions = 'Standard goods in transit liability terms apply. Demurrage charged after 24 hours of reporting.';
    public string $notes = '';

    // View Modal
    public bool $showDetailModal = false;
    public ?Quotation $viewingQuotation = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_quotations') && !$user->hasPermission('manage_quotations')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage quotations.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $quotations = Quotation::where('company_id', $companyId)
            ->with(['customer', 'branch', 'convertedBooking'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('quotation_number', 'like', '%' . $this->search . '%')
                      ->orWhere('cargo_description', 'like', '%' . $this->search . '%')
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

        // Compute Live Calculation Preview
        $previewCalc = app(FreightCalculationService::class)->calculateFreight(
            $this->rate_type,
            $this->base_rate,
            [
                'weight_tons' => $this->weight_tons,
                'distance_km' => $this->distance_km ?? 0.0,
                'quantity' => $this->package_count,
            ],
            [
                'loading' => $this->loading_charges,
                'unloading' => $this->unloading_charges,
                'toll' => $this->toll_charges,
                'detention' => $this->detention_charges,
                'other' => $this->other_charges,
            ],
            $this->discount_percentage,
            $this->tax_percentage
        );

        return view('livewire.commercial.quotation-manager', [
            'quotations' => $quotations,
            'customers' => $customers,
            'branches' => $branches,
            'contracts' => $contracts,
            'previewCalc' => $previewCalc,
        ]);
    }

    public function updatedCustomerId(?int $val): void
    {
        if ($val) {
            $customer = Customer::find($val);
            if ($customer) {
                $this->tax_percentage = $customer->is_tax_exempt ? 0.00 : 16.00;
                $activeContract = $customer->activeContract;
                if ($activeContract) {
                    $this->customer_contract_id = $activeContract->id;
                    $this->rate_type = $activeContract->rate_type;
                    $this->base_rate = (float) $activeContract->base_rate;
                    $this->discount_percentage = (float) $activeContract->discount_percentage;
                }
            }
        }
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->selectedQuotationId = null;
        $this->customer_id = null;
        $this->customer_contract_id = null;
        $this->branch_id = null;
        $this->quotation_date = now()->toDateString();
        $this->valid_until = now()->addDays(15)->toDateString();
        $this->origin_city = 'Karachi';
        $this->destination_city = 'Lahore';
        $this->origin_address = '';
        $this->destination_address = '';
        $this->cargo_description = '';
        $this->cargo_category = 'GENERAL_CARGO';
        $this->weight_tons = 30.00;
        $this->volume_cbm = 60.00;
        $this->package_count = 600;
        $this->required_vehicle_category = 'HCV';
        $this->required_vehicle_type = 'TRAILER_22W';
        $this->rate_type = 'fixed';
        $this->base_rate = 95000.00;
        $this->distance_km = 1250.00;
        $this->loading_charges = 2500.00;
        $this->unloading_charges = 2500.00;
        $this->toll_charges = 4500.00;
        $this->detention_charges = 0.00;
        $this->other_charges = 0.00;
        $this->discount_percentage = 0.00;
        $this->tax_percentage = 16.00;
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function saveQuotation(): void
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
            'rate_type' => ['required', 'string'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date'],
        ];

        $this->validate($rules);

        $quotation = app(QuotationService::class)->createQuotation($company, [
            'customer_id' => $this->customer_id,
            'customer_contract_id' => $this->customer_contract_id,
            'branch_id' => $this->branch_id,
            'quotation_date' => $this->quotation_date,
            'valid_until' => $this->valid_until,
            'origin_city' => $this->origin_city,
            'destination_city' => $this->destination_city,
            'origin_address' => $this->origin_address,
            'destination_address' => $this->destination_address,
            'cargo_description' => $this->cargo_description,
            'cargo_category' => $this->cargo_category,
            'weight_tons' => $this->weight_tons,
            'volume_cbm' => $this->volume_cbm,
            'package_count' => $this->package_count,
            'required_vehicle_category' => $this->required_vehicle_category,
            'required_vehicle_type' => $this->required_vehicle_type,
            'rate_type' => $this->rate_type,
            'base_rate' => $this->base_rate,
            'distance_km' => $this->distance_km,
            'loading_charges' => $this->loading_charges,
            'unloading_charges' => $this->unloading_charges,
            'toll_charges' => $this->toll_charges,
            'detention_charges' => $this->detention_charges,
            'other_charges' => $this->other_charges,
            'discount_percentage' => $this->discount_percentage,
            'tax_percentage' => $this->tax_percentage,
            'terms_and_conditions' => $this->terms_and_conditions,
            'notes' => $this->notes,
        ]);

        session()->flash('success', sprintf('Quotation [%s] created with net amount PKR %s.', $quotation->quotation_number, number_format($quotation->net_total_amount, 2)));
        $this->showModal = false;
        $this->resetForm();
    }

    public function approveQuotation(int $quotationId): void
    {
        $this->checkAuthorization();
        /** @var Quotation $quotation */
        $quotation = Quotation::withoutGlobalScopes()->findOrFail($quotationId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($quotation);

        app(QuotationService::class)->approveQuotation($quotation);
        session()->flash('success', sprintf('Quotation [%s] approved.', $quotation->quotation_number));
    }

    public function convertToBooking(int $quotationId): void
    {
        $this->checkAuthorization();
        /** @var Quotation $quotation */
        $quotation = Quotation::withoutGlobalScopes()->findOrFail($quotationId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($quotation);

        $booking = app(QuotationService::class)->convertToBooking($quotation);
        session()->flash('success', sprintf('Quotation [%s] converted into confirmed Booking [%s].', $quotation->quotation_number, $booking->booking_number));
    }

    public function viewQuotation(int $quotationId): void
    {
        $this->checkAuthorization();
        $this->viewingQuotation = Quotation::withoutGlobalScopes()
            ->with(['customer', 'branch', 'convertedBooking', 'creator', 'approver'])
            ->findOrFail($quotationId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingQuotation);

        $this->showDetailModal = true;
    }
}
