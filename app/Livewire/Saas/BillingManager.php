<?php

namespace App\Livewire\Saas;

use App\Models\Company;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Services\AuditLogService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;
use Livewire\WithPagination;

class BillingManager extends Component
{
    use WithPagination;

    public string $activeTab = 'invoices'; // 'invoices' or 'payments'
    public string $invoiceStatusFilter = '';
    public string $paymentStatusFilter = '';

    // Record Payment Modal
    public bool $showPaymentModal = false;
    public ?int $selectedInvoiceId = null;
    public ?SubscriptionInvoice $selectedInvoice = null;
    public float $paymentAmount = 0.00;
    public string $paymentMethod = 'bank_transfer';
    public string $paymentReference = '';
    public string $paymentNotes = '';
    public bool $autoVerify = false;

    protected function checkSuperAdmin(): void
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            throw new AuthorizationException('Access denied: This module requires Platform Super Administrator authority.');
        }
    }

    public function render()
    {
        $this->checkSuperAdmin();

        $invoices = SubscriptionInvoice::with(['company', 'subscription.plan'])
            ->when($this->invoiceStatusFilter, function ($query) {
                $query->where('status', $this->invoiceStatusFilter);
            })
            ->latest()
            ->paginate(10, ['*'], 'invoices_page');

        $payments = SubscriptionPayment::with(['company', 'invoice', 'verifier'])
            ->when($this->paymentStatusFilter, function ($query) {
                $query->where('status', $this->paymentStatusFilter);
            })
            ->latest()
            ->paginate(10, ['*'], 'payments_page');

        $totalOutstanding = (float) SubscriptionInvoice::whereIn('status', ['unpaid', 'partially_paid'])->sum('balance_amount');
        $totalCollected = (float) SubscriptionPayment::where('status', 'verified')->sum('amount');
        $pendingVerificationCount = SubscriptionPayment::where('status', 'pending_verification')->count();

        return view('livewire.saas.billing-manager', [
            'invoices' => $invoices,
            'payments' => $payments,
            'totalOutstanding' => $totalOutstanding,
            'totalCollected' => $totalCollected,
            'pendingVerificationCount' => $pendingVerificationCount,
        ]);
    }

    public function openRecordPaymentModal(int $invoiceId): void
    {
        $this->checkSuperAdmin();
        $invoice = SubscriptionInvoice::with('company')->findOrFail($invoiceId);

        $this->selectedInvoiceId = $invoice->id;
        $this->selectedInvoice = $invoice;
        $this->paymentAmount = (float) $invoice->balance_amount;
        $this->paymentMethod = 'bank_transfer';
        $this->paymentReference = '';
        $this->paymentNotes = '';
        $this->showPaymentModal = true;
    }

    public function savePayment(): void
    {
        $this->checkSuperAdmin();

        $this->validate([
            'paymentAmount' => ['required', 'numeric', 'min:1'],
            'paymentMethod' => ['required', 'string'],
            'paymentReference' => ['nullable', 'string', 'max:100'],
            'paymentNotes' => ['nullable', 'string'],
        ]);

        $invoice = SubscriptionInvoice::findOrFail($this->selectedInvoiceId);

        app(SubscriptionService::class)->recordPayment(
            $invoice,
            $this->paymentAmount,
            $this->paymentMethod,
            $this->paymentReference,
            $this->paymentNotes,
            $this->autoVerify
        );

        session()->flash('success', sprintf('Payment of PKR %s recorded and applied to invoice [%s].', number_format($this->paymentAmount, 2), $invoice->invoice_number));
        $this->showPaymentModal = false;
        $this->selectedInvoice = null;
    }

    public function verifyPayment(int $paymentId): void
    {
        $this->checkSuperAdmin();
        $payment = SubscriptionPayment::with('invoice')->findOrFail($paymentId);

        app(SubscriptionService::class)->verifyPayment($payment, auth()->id());

        app(AuditLogService::class)->log(
            'payment_verified',
            $payment,
            null,
            $payment->toArray(),
            sprintf('Super Admin verified subscription payment [%s].', $payment->payment_number)
        );

        session()->flash('success', sprintf('Payment [%s] verified successfully.', $payment->payment_number));
    }

    public function rejectPayment(int $paymentId): void
    {
        $this->checkSuperAdmin();
        $payment = SubscriptionPayment::findOrFail($paymentId);

        $payment->status = 'rejected';
        $payment->verified_by = auth()->id();
        $payment->verified_at = now();
        $payment->save();

        session()->flash('error', sprintf('Payment [%s] marked as rejected.', $payment->payment_number));
    }
}

