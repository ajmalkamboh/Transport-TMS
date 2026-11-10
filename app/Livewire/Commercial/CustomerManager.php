<?php

namespace App\Livewire\Commercial;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerContract;
use App\Models\CustomerDeliveryAddress;
use App\Services\AuditLogService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';

    // Customer Modal
    public bool $showCustomerModal = false;
    public bool $isEditingCustomer = false;
    public ?int $selectedCustomerId = null;

    public string $customer_code = '';
    public string $customer_type = Customer::TYPE_CORPORATE;
    public string $name = '';
    public string $company_name = '';
    public string $contact_person = '';
    public string $designation = '';
    public string $phone = '';
    public string $mobile = '';
    public string $email = '';
    public string $tax_ntn = '';
    public string $tax_strn = '';
    public bool $is_tax_exempt = false;
    public string $billing_address = '';
    public string $billing_city = 'Lahore';
    public string $billing_state = 'Punjab';
    public string $billing_postal_code = '';
    public float $credit_limit = 500000.00;
    public int $credit_days = 30;
    public string $payment_terms = Customer::TERMS_CREDIT_30;
    public float $opening_balance = 0.00;
    public string $status = Customer::STATUS_ACTIVE;
    public ?int $branch_id = null;
    public string $notes = '';

    // Delivery Address Modal
    public bool $showAddressModal = false;
    public ?Customer $addressCustomer = null;
    public string $location_title = '';
    public string $address_contact_person = '';
    public string $address_contact_phone = '';
    public string $address_text = '';
    public string $address_city = '';
    public bool $address_is_default = false;

    // Contract Modal
    public bool $showContractModal = false;
    public ?Customer $contractCustomer = null;
    public string $contract_number = '';
    public string $contract_title = '';
    public string $contract_rate_type = 'route_based';
    public float $contract_base_rate = 95000.00;
    public string $contract_payment_terms = 'credit_30_days';
    public int $contract_credit_days = 30;
    public float $contract_credit_limit = 1000000.00;
    public float $contract_discount_percentage = 0.00;
    public float $contract_tax_percentage = 16.00;
    public float $contract_loading_charges = 2500.00;
    public float $contract_unloading_charges = 2500.00;
    public float $contract_detention_rate = 5000.00;
    public string $contract_start_date = '';
    public ?string $contract_end_date = null;
    public string $contract_notes = '';

    // Customer Dossier Modal
    public bool $showDetailModal = false;
    public ?Customer $viewingCustomer = null;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_customers') && !$user->hasPermission('manage_customers')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage commercial customers.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->with(['branch', 'contracts', 'deliveryAddresses'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('customer_code', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%')
                      ->orWhere('tax_ntn', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->typeFilter, fn($q) => $q->where('customer_type', $this->typeFilter))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(10);

        $branches = Branch::where('company_id', $companyId)->where('is_active', true)->get();

        return view('livewire.commercial.customer-manager', [
            'customers' => $customers,
            'branches' => $branches,
        ]);
    }

    public function openCreateCustomerModal(): void
    {
        $this->checkAuthorization();
        $this->resetCustomerForm();
        $this->isEditingCustomer = false;
        $this->showCustomerModal = true;
    }

    public function resetCustomerForm(): void
    {
        $this->selectedCustomerId = null;
        $this->customer_code = 'CUST-' . date('Y') . '-' . rand(100, 999);
        $this->customer_type = Customer::TYPE_CORPORATE;
        $this->name = '';
        $this->company_name = '';
        $this->contact_person = '';
        $this->designation = '';
        $this->phone = '';
        $this->mobile = '';
        $this->email = '';
        $this->tax_ntn = '';
        $this->tax_strn = '';
        $this->is_tax_exempt = false;
        $this->billing_address = '';
        $this->billing_city = 'Lahore';
        $this->billing_state = 'Punjab';
        $this->billing_postal_code = '';
        $this->credit_limit = 500000.00;
        $this->credit_days = 30;
        $this->payment_terms = Customer::TERMS_CREDIT_30;
        $this->opening_balance = 0.00;
        $this->status = Customer::STATUS_ACTIVE;
        $this->branch_id = null;
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function editCustomer(int $customerId): void
    {
        $this->checkAuthorization();

        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($customer);

        $this->selectedCustomerId = $customer->id;
        $this->customer_code = $customer->customer_code ?? '';
        $this->customer_type = $customer->customer_type;
        $this->name = $customer->name;
        $this->company_name = $customer->company_name ?? '';
        $this->contact_person = $customer->contact_person ?? '';
        $this->designation = $customer->designation ?? '';
        $this->phone = $customer->phone;
        $this->mobile = $customer->mobile ?? '';
        $this->email = $customer->email ?? '';
        $this->tax_ntn = $customer->tax_ntn ?? '';
        $this->tax_strn = $customer->tax_strn ?? '';
        $this->is_tax_exempt = (bool) $customer->is_tax_exempt;
        $this->billing_address = $customer->billing_address ?? '';
        $this->billing_city = $customer->billing_city ?? '';
        $this->billing_state = $customer->billing_state ?? '';
        $this->billing_postal_code = $customer->billing_postal_code ?? '';
        $this->credit_limit = (float) $customer->credit_limit;
        $this->credit_days = (int) $customer->credit_days;
        $this->payment_terms = $customer->payment_terms;
        $this->opening_balance = (float) $customer->opening_balance;
        $this->status = $customer->status;
        $this->branch_id = $customer->branch_id;
        $this->notes = $customer->notes ?? '';

        $this->isEditingCustomer = true;
        $this->showCustomerModal = true;
    }

    public function saveCustomer(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'customer_type' => ['required', Rule::in([Customer::TYPE_CORPORATE, Customer::TYPE_INDIVIDUAL])],
            'company_name' => ['nullable', 'string', 'max:191'],
            'contact_person' => ['nullable', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_ntn' => ['nullable', 'string', 'max:50'],
            'tax_strn' => ['nullable', 'string', 'max:50'],
            'is_tax_exempt' => ['boolean'],
            'billing_address' => ['nullable', 'string'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'credit_days' => ['required', 'integer', 'min:0'],
            'payment_terms' => ['required', 'string'],
            'status' => ['required', Rule::in([Customer::STATUS_ACTIVE, Customer::STATUS_INACTIVE, Customer::STATUS_BLACKLISTED])],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
        ];

        $validated = $this->validate($rules);

        if ($this->isEditingCustomer) {
            /** @var Customer $customer */
            $customer = Customer::withoutGlobalScopes()->findOrFail($this->selectedCustomerId);
            app(TenantAuthorizationService::class)->validateTenantOwnership($customer);

            $old = $customer->toArray();
            $customer->update($validated);

            app(AuditLogService::class)->log('customer_updated', $customer, $old, $customer->toArray(), 'Customer account updated.');
            session()->flash('success', sprintf('Customer [%s] profile updated.', $customer->name));
        } else {
            $customer = Customer::create(array_merge($validated, [
                'company_id' => $companyId,
                'customer_code' => $this->customer_code,
                'opening_balance' => $this->opening_balance,
                'current_balance' => $this->opening_balance,
            ]));

            app(AuditLogService::class)->log('customer_created', $customer, null, $customer->toArray(), 'New customer registered.');
            session()->flash('success', sprintf('Customer [%s] successfully registered.', $customer->name));
        }

        $this->showCustomerModal = false;
        $this->resetCustomerForm();
    }

    public function openAddressModal(int $customerId): void
    {
        $this->checkAuthorization();
        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($customer);

        $this->addressCustomer = $customer;
        $this->location_title = '';
        $this->address_contact_person = $customer->contact_person ?? '';
        $this->address_contact_phone = $customer->phone ?? '';
        $this->address_text = '';
        $this->address_city = $customer->billing_city ?? 'Lahore';
        $this->address_is_default = false;
        $this->showAddressModal = true;
    }

    public function saveDeliveryAddress(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'location_title' => ['required', 'string', 'max:191'],
            'address_text' => ['required', 'string'],
            'address_city' => ['required', 'string', 'max:100'],
        ]);

        CustomerDeliveryAddress::create([
            'company_id' => $this->addressCustomer->company_id,
            'customer_id' => $this->addressCustomer->id,
            'location_title' => $this->location_title,
            'contact_person' => $this->address_contact_person,
            'contact_phone' => $this->address_contact_phone,
            'address' => $this->address_text,
            'city' => $this->address_city,
            'is_default' => $this->address_is_default,
        ]);

        session()->flash('success', 'Delivery address added.');
        $this->showAddressModal = false;
    }

    public function openContractModal(int $customerId): void
    {
        $this->checkAuthorization();
        /** @var Customer $customer */
        $customer = Customer::withoutGlobalScopes()->findOrFail($customerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($customer);

        $this->contractCustomer = $customer;
        $this->contract_number = 'CNT-CUST-' . date('Y') . '-' . rand(1000, 9999);
        $this->contract_title = 'Annual Freight Agreement - ' . ($customer->company_name ?: $customer->name);
        $this->contract_rate_type = 'route_based';
        $this->contract_base_rate = 95000.00;
        $this->contract_payment_terms = $customer->payment_terms;
        $this->contract_credit_days = $customer->credit_days;
        $this->contract_credit_limit = (float) $customer->credit_limit;
        $this->contract_discount_percentage = 0.00;
        $this->contract_tax_percentage = $customer->is_tax_exempt ? 0.00 : 16.00;
        $this->contract_loading_charges = 2500.00;
        $this->contract_unloading_charges = 2500.00;
        $this->contract_detention_rate = 5000.00;
        $this->contract_start_date = now()->toDateString();
        $this->contract_end_date = now()->addYear()->toDateString();
        $this->contract_notes = '';
        $this->showContractModal = true;
    }

    public function saveContract(): void
    {
        $this->checkAuthorization();
        $this->validate([
            'contract_number' => ['required', 'string', 'max:50'],
            'contract_title' => ['required', 'string', 'max:191'],
            'contract_rate_type' => ['required', 'string'],
            'contract_base_rate' => ['required', 'numeric', 'min:0'],
            'contract_start_date' => ['required', 'date'],
        ]);

        CustomerContract::create([
            'company_id' => $this->contractCustomer->company_id,
            'customer_id' => $this->contractCustomer->id,
            'contract_number' => $this->contract_number,
            'contract_title' => $this->contract_title,
            'rate_type' => $this->contract_rate_type,
            'base_rate' => $this->contract_base_rate,
            'payment_terms' => $this->contract_payment_terms,
            'credit_days' => $this->contract_credit_days,
            'credit_limit' => $this->contract_credit_limit,
            'discount_percentage' => $this->contract_discount_percentage,
            'tax_percentage' => $this->contract_tax_percentage,
            'loading_charges' => $this->contract_loading_charges,
            'unloading_charges' => $this->contract_unloading_charges,
            'detention_rate_per_day' => $this->contract_detention_rate,
            'start_date' => $this->contract_start_date,
            'end_date' => $this->contract_end_date,
            'status' => 'active',
            'notes' => $this->contract_notes,
        ]);

        session()->flash('success', sprintf('Customer commercial contract [%s] established.', $this->contract_number));
        $this->showContractModal = false;
    }

    public function viewCustomer(int $customerId): void
    {
        $this->checkAuthorization();
        $this->viewingCustomer = Customer::withoutGlobalScopes()
            ->with(['contracts', 'deliveryAddresses', 'bookings' => fn($q) => $q->latest()->limit(5)])
            ->findOrFail($customerId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($this->viewingCustomer);

        $this->showDetailModal = true;
    }
}

