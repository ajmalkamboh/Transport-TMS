<?php

namespace App\Livewire\Finance;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Trip;
use App\Services\BillingAndReceivableService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerBillingManager extends Component
{
    use WithPagination;

    public string $activeTab = 'invoices'; // 'invoices', 'receipts'
    public string $search = '';
    public string $statusFilter = '';
    public ?int $customerFilter = null;

    // Create Invoice Modal State
    public bool $showCreateInvoiceModal = false;
    public ?int $selectedCustomerId = null;
    public ?int $selectedTripId = null;
    public string $invoiceDate = '';
    public string $dueDate = '';
    public float $taxRatePercent = 0.00;
    public float $discountAmount = 0.00;
    public string $notes = '';
    public array $invoiceItems = [];

    // Record Payment Modal State
    public bool $showPaymentModal = false;
    public ?int $paymentCustomerId = null;
    public ?int $paymentInvoiceId = null;
    public ?int $paymentBankAccountId = null;
    public string $paymentType = CustomerPayment::TYPE_INVOICE_PAYMENT;
    public float $paymentAmount = 0.00;
    public string $paymentDate = '';
    public string $paymentMethod = CustomerPayment::METHOD_BANK_TRANSFER;
    public string $referenceNumber = '';
    public string $chequeNumber = '';

    // Apply Advance Modal State
    public bool $showApplyAdvanceModal = false;
    public ?int $selectedAdvanceId = null;
    public ?int $targetInvoiceId = null;
    public float $allocationAmount = 0.00;

    public function mount(): void
    {
        $this->invoiceDate = now()->toDateString();
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->paymentDate = now()->toDateString();
        $this->resetInvoiceItems();
    }

    public function resetInvoiceItems(): void
    {
        $this->invoiceItems = [
            ['description' => 'Freight Transportation Charges', 'quantity' => 1, 'unit_rate' => 0.00, 'amount' => 0.00],
        ];
    }

    public function addInvoiceItem(): void
    {
        $this->invoiceItems[] = ['description' => '', 'quantity' => 1, 'unit_rate' => 0.00, 'amount' => 0.00];
    }

    public function removeInvoiceItem(int $index): void
    {
        if (count($this->invoiceItems) > 1) {
            unset($this->invoiceItems[$index]);
            $this->invoiceItems = array_values($this->invoiceItems);
        }
    }

    public function openCreateInvoiceModal(): void
    {
        $this->invoiceDate = now()->toDateString();
        $this->dueDate = now()->addDays(30)->toDateString();
        $this->taxRatePercent = 0.00;
        $this->discountAmount = 0.00;
        $this->notes = '';
        $this->resetInvoiceItems();
        $this->showCreateInvoiceModal = true;
    }

    public function saveInvoice(): void
    {
        $this->validate([
            'selectedCustomerId' => 'required|exists:customers,id',
            'invoiceDate' => 'required|date',
            'dueDate' => 'required|date',
            'invoiceItems' => 'required|array|min:1',
            'invoiceItems.*.description' => 'required|string',
            'invoiceItems.*.amount' => 'required|numeric|min:0.01',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        try {
            $invoice = app(BillingAndReceivableService::class)->createInvoice([
                'company_id' => $companyId,
                'customer_id' => $this->selectedCustomerId,
                'trip_id' => $this->selectedTripId,
                'invoice_date' => $this->invoiceDate,
                'due_date' => $this->dueDate,
                'tax_rate_percent' => $this->taxRatePercent,
                'discount_amount' => $this->discountAmount,
                'notes' => $this->notes,
                'items' => $this->invoiceItems,
            ], Auth::user());

            session()->flash('success', "Invoice [{$invoice->invoice_number}] created successfully as draft.");
            $this->showCreateInvoiceModal = false;
        } catch (\Throwable $e) {
            $this->addError('invoiceItems', $e->getMessage());
        }
    }

    public function postInvoice(int $invoiceId): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $invoice = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($invoiceId);

        try {
            app(BillingAndReceivableService::class)->postInvoice($invoice, Auth::user());
            session()->flash('success', "Invoice [{$invoice->invoice_number}] posted to General Ledger.");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function openPaymentModal(?int $invoiceId = null, ?int $customerId = null): void
    {
        $this->paymentInvoiceId = $invoiceId;
        $this->paymentCustomerId = $customerId;
        $this->paymentAmount = 0.00;
        $this->paymentDate = now()->toDateString();
        $this->paymentType = $invoiceId ? CustomerPayment::TYPE_INVOICE_PAYMENT : CustomerPayment::TYPE_CUSTOMER_ADVANCE;

        if ($invoiceId) {
            $inv = CustomerInvoice::withoutGlobalScopes()->find($invoiceId);
            if ($inv) {
                $this->paymentCustomerId = $inv->customer_id;
                $this->paymentAmount = (float) $inv->balance_due;
            }
        }

        $this->showPaymentModal = true;
    }

    public function savePayment(): void
    {
        $this->validate([
            'paymentCustomerId' => 'required|exists:customers,id',
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentDate' => 'required|date',
            'paymentMethod' => 'required|string',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        try {
            $payment = app(BillingAndReceivableService::class)->recordCustomerPayment([
                'company_id' => $companyId,
                'customer_id' => $this->paymentCustomerId,
                'customer_invoice_id' => $this->paymentInvoiceId,
                'bank_account_id' => $this->paymentBankAccountId,
                'amount' => $this->paymentAmount,
                'payment_type' => $this->paymentType,
                'payment_method' => $this->paymentMethod,
                'receipt_date' => $this->paymentDate,
                'reference_number' => $this->referenceNumber,
                'cheque_number' => $this->chequeNumber,
            ], Auth::user());

            session()->flash('success', "Payment Receipt [{$payment->receipt_number}] recorded successfully.");
            $this->showPaymentModal = false;
        } catch (\Throwable $e) {
            $this->addError('paymentAmount', $e->getMessage());
        }
    }

    public function openApplyAdvanceModal(int $paymentId): void
    {
        $this->selectedAdvanceId = $paymentId;
        $advance = CustomerPayment::withoutGlobalScopes()->findOrFail($paymentId);
        $this->allocationAmount = (float) $advance->unapplied_amount;
        $this->targetInvoiceId = null;
        $this->showApplyAdvanceModal = true;
    }

    public function confirmApplyAdvance(): void
    {
        $this->validate([
            'selectedAdvanceId' => 'required|exists:customer_payments,id',
            'targetInvoiceId' => 'required|exists:customer_invoices,id',
            'allocationAmount' => 'required|numeric|min:0.01',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $advance = CustomerPayment::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($this->selectedAdvanceId);
        $invoice = CustomerInvoice::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($this->targetInvoiceId);

        try {
            app(BillingAndReceivableService::class)->applyCustomerAdvance($advance, $invoice, $this->allocationAmount, Auth::user());
            session()->flash('success', "Advance applied successfully to Invoice [{$invoice->invoice_number}].");
            $this->showApplyAdvanceModal = false;
        } catch (\Throwable $e) {
            $this->addError('allocationAmount', $e->getMessage());
        }
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $customers = Customer::withoutGlobalScopes()->where('company_id', $companyId)->orderBy('name')->get();
        $trips = Trip::withoutGlobalScopes()->where('company_id', $companyId)->latest('id')->take(30)->get();
        $bankAccounts = BankAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('is_active', true)->get();

        // Invoices Query
        $invQuery = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['customer', 'trip', 'voucher']);

        if ($this->customerFilter) {
            $invQuery->where('customer_id', $this->customerFilter);
        }

        if ($this->statusFilter) {
            $invQuery->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $invQuery->where(function ($q) {
                $q->where('invoice_number', 'like', "%{$this->search}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"));
            });
        }

        $invoices = $invQuery->orderByDesc('invoice_date')->orderByDesc('id')->paginate(15);

        // Receipts Query
        $recQuery = CustomerPayment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['customer', 'invoice', 'voucher', 'bankAccount']);

        if ($this->customerFilter) {
            $recQuery->where('customer_id', $this->customerFilter);
        }

        if ($this->search) {
            $recQuery->where(function ($q) {
                $q->where('receipt_number', 'like', "%{$this->search}%")
                  ->orWhere('reference_number', 'like', "%{$this->search}%")
                  ->orWhereHas('customer', fn($c) => $c->where('name', 'like', "%{$this->search}%"));
            });
        }

        $receipts = $recQuery->orderByDesc('receipt_date')->orderByDesc('id')->paginate(15);

        // Open invoices for selected advance modal
        $openInvoicesForAdvance = [];
        if ($this->selectedAdvanceId) {
            $adv = CustomerPayment::withoutGlobalScopes()->find($this->selectedAdvanceId);
            if ($adv) {
                $openInvoicesForAdvance = CustomerInvoice::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('customer_id', $adv->customer_id)
                    ->whereIn('status', [CustomerInvoice::STATUS_POSTED, CustomerInvoice::STATUS_PARTIALLY_PAID])
                    ->where('balance_due', '>', 0)
                    ->get();
            }
        }

        return view('livewire.finance.customer-billing-manager', compact(
            'invoices',
            'receipts',
            'customers',
            'trips',
            'bankAccounts',
            'openInvoicesForAdvance'
        ));
    }
}
