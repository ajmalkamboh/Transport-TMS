<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceItem;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\DocumentSequence;
use App\Models\JournalVoucher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingAndReceivableService
{
    public function __construct(
        protected GeneralLedgerService $generalLedger,
        protected DocumentSequenceService $sequenceService,
        protected ChartOfAccountsService $coaService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Create a customer invoice draft.
     */
    public function createInvoice(array $data, ?User $user = null): CustomerInvoice
    {
        $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Company context is required to create a customer invoice.');
        }

        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException('An invoice must contain at least one line item.');
        }

        return DB::transaction(function () use ($companyId, $data, $items, $user) {
            $invoiceNumber = $data['invoice_number'] ?? $this->sequenceService->getNextSequenceNumber(
                documentType: DocumentSequence::TYPE_INVOICE,
                prefix: 'INV',
                explicitCompanyId: $companyId,
                explicitBranchId: $data['branch_id'] ?? null
            );

            /** @var CustomerInvoice $invoice */
            $invoice = CustomerInvoice::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'trip_id' => $data['trip_id'] ?? null,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                'tax_rate_percent' => $data['tax_rate_percent'] ?? 0.00,
                'discount_amount' => $data['discount_amount'] ?? 0.00,
                'status' => CustomerInvoice::STATUS_DRAFT,
                'payment_status' => CustomerInvoice::PAYMENT_UNPAID,
                'created_by' => $user?->id ?? $data['created_by'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $itemData) {
                $qty = (float) ($itemData['quantity'] ?? 1);
                $rate = (float) ($itemData['unit_rate'] ?? 0);
                $amount = round((float) ($itemData['amount'] ?? ($qty * $rate)), 2);

                CustomerInvoiceItem::create([
                    'company_id' => $companyId,
                    'customer_invoice_id' => $invoice->id,
                    'consignment_id' => $itemData['consignment_id'] ?? null,
                    'item_description' => $itemData['item_description'] ?? $itemData['description'] ?? 'Freight Transportation Charges',
                    'quantity' => $qty,
                    'unit_rate' => $rate,
                    'amount' => $amount,
                ]);
            }

            $invoice->recalculateTotals();

            $this->auditLog->log(
                action: 'customer_invoice_created',
                model: $invoice,
                oldValues: [],
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_id' => $invoice->customer_id,
                    'total_amount' => $invoice->total_amount,
                ],
                description: "Customer invoice [{$invoice->invoice_number}] created"
            );

            return $invoice->load(['items', 'customer']);
        });
    }

    /**
     * Post a Customer Invoice to the General Ledger.
     * Generates a balanced Journal Voucher:
     * Dr. 1100 Accounts Receivable (Asset)
     * Cr. 4100 Freight Revenue (Revenue)
     * Cr. 2190 / Output Tax Payable (Liability, if tax applicable)
     */
    public function postInvoice(CustomerInvoice $invoice, ?User $user = null): CustomerInvoice
    {
        if ($invoice->isPosted()) {
            throw new InvalidArgumentException("Invoice [{$invoice->invoice_number}] has already been posted.");
        }

        if ((float) $invoice->total_amount <= 0) {
            throw new InvalidArgumentException("Cannot post invoice [{$invoice->invoice_number}] with zero or negative total amount.");
        }

        return DB::transaction(function () use ($invoice, $user) {
            $companyId = $invoice->company_id;

            // Resolve AR and Revenue accounts
            $arAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
            $revAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_FREIGHT_REVENUE);

            if (!$arAccount || !$revAccount) {
                // Ensure default COA exists
                $this->coaService->seedDefaultChartOfAccounts($invoice->company);
                $arAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
                $revAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_FREIGHT_REVENUE);
            }

            $voucherItems = [];

            // 1. Dr. Accounts Receivable (Gross Total)
            $voucherItems[] = [
                'chart_of_account_id' => $arAccount->id,
                'debit' => (float) $invoice->total_amount,
                'credit' => 0.00,
                'description' => "Freight Receivable - Invoice {$invoice->invoice_number}",
                'customer_id' => $invoice->customer_id,
                'trip_id' => $invoice->trip_id,
            ];

            // 2. Cr. Freight Revenue (Subtotal - Discount)
            $netRevenue = round((float) $invoice->subtotal - (float) $invoice->discount_amount, 2);
            $voucherItems[] = [
                'chart_of_account_id' => $revAccount->id,
                'debit' => 0.00,
                'credit' => $netRevenue,
                'description' => "Freight Transportation Services - Invoice {$invoice->invoice_number}",
                'customer_id' => $invoice->customer_id,
                'trip_id' => $invoice->trip_id,
            ];

            // 3. Cr. Tax Payable (if tax applies)
            if ((float) $invoice->tax_amount > 0) {
                // Find or use admin tax payable (or other deductions clearing 2190)
                $taxAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where(function ($q) {
                        $q->where('account_code', '2190')
                          ->orWhere('account_group', 'current_liability');
                    })
                    ->first() ?? $arAccount; // fallback safety

                $voucherItems[] = [
                    'chart_of_account_id' => $taxAccount->id,
                    'debit' => 0.00,
                    'credit' => (float) $invoice->tax_amount,
                    'description' => "Sales Tax / VAT on Freight - Invoice {$invoice->invoice_number}",
                    'customer_id' => $invoice->customer_id,
                    'trip_id' => $invoice->trip_id,
                ];
            }

            // Post General Ledger Voucher
            $voucher = $this->generalLedger->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $invoice->branch_id,
                'voucher_date' => $invoice->invoice_date->toDateString(),
                'voucher_type' => JournalVoucher::TYPE_JOURNAL,
                'source_module' => 'customer_invoice',
                'source_id' => $invoice->id,
                'idempotency_key' => "INV-POST-{$invoice->id}-{$invoice->invoice_number}",
                'reference_number' => $invoice->invoice_number,
                'narration' => "Customer Freight Invoice {$invoice->invoice_number} - Customer: {$invoice->customer?->name}",
                'trip_id' => $invoice->trip_id,
                'items' => $voucherItems,
            ], $user);

            // Update Invoice Status
            $invoice->update([
                'status' => CustomerInvoice::STATUS_POSTED,
                'journal_voucher_id' => $voucher->id,
                'posted_by' => $user?->id,
                'posted_at' => now(),
            ]);

            return $invoice->fresh(['items', 'voucher', 'customer']);
        });
    }

    /**
     * Record a customer payment receipt.
     * Supports:
     * 1. Direct Invoice Payment (Dr. Cash/Bank, Cr. Accounts Receivable)
     * 2. Customer Advance (Dr. Cash/Bank, Cr. 2200-CUSTOMER-ADVANCES as Liability)
     * 3. On Account Receipt (unapplied advance liability)
     */
    public function recordCustomerPayment(array $data, ?User $user = null): CustomerPayment
    {
        $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Company context is required to record a customer payment.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment receipt amount must be greater than 0.');
        }

        $paymentType = $data['payment_type'] ?? CustomerPayment::TYPE_INVOICE_PAYMENT;
        $customerId = $data['customer_id'];
        $invoiceId = $data['customer_invoice_id'] ?? null;

        return DB::transaction(function () use ($companyId, $data, $amount, $paymentType, $customerId, $invoiceId, $user) {
            $receiptNumber = $data['receipt_number'] ?? $this->sequenceService->getNextSequenceNumber(
                documentType: DocumentSequence::TYPE_RECEIPT,
                prefix: 'RCP',
                explicitCompanyId: $companyId,
                explicitBranchId: $data['branch_id'] ?? null
            );

            // Resolve receiving Bank/Cash account
            $bankAccountId = $data['bank_account_id'] ?? null;
            $assetAccount = null;

            if ($bankAccountId) {
                $bankAccount = BankAccount::withoutGlobalScopes()->where('company_id', $companyId)->find($bankAccountId);
                if ($bankAccount && $bankAccount->chart_of_account_id) {
                    $assetAccount = ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->find($bankAccount->chart_of_account_id);
                }
            }

            if (!$assetAccount) {
                $assetAccount = $this->coaService->getAccountByCode(
                    $companyId,
                    ($data['payment_method'] ?? 'cash') === 'cash' ? ChartOfAccount::CODE_CASH_IN_HAND : ChartOfAccount::CODE_BANK_ACCOUNTS
                );
            }

            if (!$assetAccount) {
                $this->coaService->seedDefaultChartOfAccounts(\App\Models\Company::findOrFail($companyId));
                $assetAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CASH_IN_HAND);
            }

            $unappliedAmount = 0.00;
            $voucherItems = [];

            // 1. Dr. Bank / Cash Account (Asset increases)
            $voucherItems[] = [
                'chart_of_account_id' => $assetAccount->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Collection Receipt {$receiptNumber} via " . ($data['payment_method'] ?? 'cash'),
                'customer_id' => $customerId,
            ];

            /** @var CustomerInvoice|null $invoice */
            $invoice = null;
            if ($invoiceId) {
                $invoice = CustomerInvoice::withoutGlobalScopes()->where('company_id', $companyId)->find($invoiceId);
            }

            if ($paymentType === CustomerPayment::TYPE_CUSTOMER_ADVANCE || (!$invoice && $paymentType !== CustomerPayment::TYPE_INVOICE_PAYMENT)) {
                // Advance or On-Account: Credit Customer Advances Liability (2200)
                $advAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CUSTOMER_ADVANCES);
                if (!$advAccount) {
                    $this->coaService->seedDefaultChartOfAccounts(\App\Models\Company::findOrFail($companyId));
                    $advAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CUSTOMER_ADVANCES);
                }

                $voucherItems[] = [
                    'chart_of_account_id' => $advAccount->id,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => "Customer Advance Deposit - Receipt {$receiptNumber}",
                    'customer_id' => $customerId,
                ];

                $unappliedAmount = $amount;
                $paymentType = CustomerPayment::TYPE_CUSTOMER_ADVANCE;
            } else {
                // Direct Invoice Payment: Credit Accounts Receivable (1100)
                $arAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
                if (!$arAccount) {
                    $this->coaService->seedDefaultChartOfAccounts(\App\Models\Company::findOrFail($companyId));
                    $arAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);
                }

                $voucherItems[] = [
                    'chart_of_account_id' => $arAccount->id,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => "Payment for Invoice " . ($invoice ? $invoice->invoice_number : 'Direct Freight'),
                    'customer_id' => $customerId,
                    'trip_id' => $invoice?->trip_id,
                ];
            }

            // Post Receipt Voucher
            $voucherType = ($data['payment_method'] ?? 'cash') === 'cash' ? JournalVoucher::TYPE_CASH_RECEIPT : JournalVoucher::TYPE_BANK_RECEIPT;
            $voucher = $this->generalLedger->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'voucher_date' => $data['receipt_date'] ?? now()->toDateString(),
                'voucher_type' => $voucherType,
                'source_module' => 'customer_payment',
                'idempotency_key' => "RCP-{$receiptNumber}",
                'reference_number' => $receiptNumber,
                'narration' => "Customer Receipt {$receiptNumber} - Ref: " . ($data['reference_number'] ?? 'Direct'),
                'trip_id' => $invoice?->trip_id,
                'items' => $voucherItems,
            ], $user);

            // If bank account was specified, adjust its current balance
            if ($bankAccountId) {
                BankAccount::withoutGlobalScopes()
                    ->where('id', $bankAccountId)
                    ->increment('current_balance', $amount);
            }

            /** @var CustomerPayment $payment */
            $payment = CustomerPayment::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'customer_id' => $customerId,
                'customer_invoice_id' => $invoice?->id,
                'bank_account_id' => $bankAccountId,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
                'amount' => $amount,
                'payment_type' => $paymentType,
                'unapplied_amount' => $unappliedAmount,
                'payment_method' => $data['payment_method'] ?? CustomerPayment::METHOD_CASH,
                'reference_number' => $data['reference_number'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'status' => CustomerPayment::STATUS_POSTED,
                'journal_voucher_id' => $voucher->id,
                'received_by' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // If direct invoice payment, apply immediately
            if ($invoice && $paymentType === CustomerPayment::TYPE_INVOICE_PAYMENT) {
                CustomerPaymentAllocation::create([
                    'company_id' => $companyId,
                    'customer_payment_id' => $payment->id,
                    'customer_invoice_id' => $invoice->id,
                    'allocated_amount' => $amount,
                    'allocated_at' => $payment->receipt_date,
                    'allocated_by' => $user?->id,
                ]);

                $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
                $invoice->recalculateTotals();
            }

            $this->auditLog->log(
                action: 'customer_payment_recorded',
                model: $payment,
                oldValues: [],
                newValues: [
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'payment_type' => $payment->payment_type,
                    'unapplied_amount' => $payment->unapplied_amount,
                ],
                description: "Customer receipt [{$payment->receipt_number}] recorded"
            );

            return $payment->fresh(['customer', 'invoice', 'allocations', 'voucher']);
        });
    }

    /**
     * Apply unallocated customer advance liability to an unpaid customer invoice.
     * Posts adjusting journal voucher:
     * Dr. 2200 Customer Advances (Liability decreases)
     * Cr. 1100 Accounts Receivable (Asset/Receivable decreases)
     */
    public function applyCustomerAdvance(
        CustomerPayment $advance,
        CustomerInvoice $invoice,
        float $amount,
        ?User $user = null
    ): CustomerPaymentAllocation {
        if ($advance->company_id !== $invoice->company_id) {
            throw new InvalidArgumentException('Cross-tenant advance application is strictly prohibited.');
        }

        if ($advance->customer_id !== $invoice->customer_id) {
            throw new InvalidArgumentException('Cannot apply advance payment of one customer to another customer invoice.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Allocation amount must be greater than 0.');
        }

        if ((float) $advance->unapplied_amount < $amount) {
            throw new InvalidArgumentException(
                sprintf('Insufficient advance balance. Available: [%0.2f], Requested: [%0.2f].', $advance->unapplied_amount, $amount)
            );
        }

        if ((float) $invoice->balance_due < $amount) {
            throw new InvalidArgumentException(
                sprintf('Allocation exceeds invoice balance due. Invoice Balance Due: [%0.2f], Requested: [%0.2f].', $invoice->balance_due, $amount)
            );
        }

        return DB::transaction(function () use ($advance, $invoice, $amount, $user) {
            $companyId = $advance->company_id;

            $advAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_CUSTOMER_ADVANCES);
            $arAccount = $this->coaService->getAccountByCode($companyId, ChartOfAccount::CODE_ACCOUNTS_RECEIVABLE);

            // Post Adjusting Journal Voucher
            $this->generalLedger->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $invoice->branch_id,
                'voucher_date' => now()->toDateString(),
                'voucher_type' => JournalVoucher::TYPE_ADJUSTMENT,
                'source_module' => 'customer_advance_allocation',
                'source_id' => $advance->id,
                'idempotency_key' => "ADV-ALLOC-{$advance->id}-INV-{$invoice->id}-" . uniqid(),
                'reference_number' => "ADV-APP-{$advance->receipt_number}",
                'narration' => "Advance applied from Receipt {$advance->receipt_number} to Invoice {$invoice->invoice_number}",
                'trip_id' => $invoice->trip_id,
                'items' => [
                    [
                        'chart_of_account_id' => $advAccount->id,
                        'debit' => $amount,
                        'credit' => 0.00,
                        'description' => "Reclassification of Advance Liability to AR - Invoice {$invoice->invoice_number}",
                        'customer_id' => $advance->customer_id,
                    ],
                    [
                        'chart_of_account_id' => $arAccount->id,
                        'debit' => 0.00,
                        'credit' => $amount,
                        'description' => "Settlement of AR via Customer Advance - Receipt {$advance->receipt_number}",
                        'customer_id' => $invoice->customer_id,
                        'trip_id' => $invoice->trip_id,
                    ],
                ],
            ], $user);

            // Update Advance unapplied amount
            $advance->decrement('unapplied_amount', $amount);

            // Update Invoice paid amount & status
            $invoice->paid_amount = round((float) $invoice->paid_amount + $amount, 2);
            $invoice->recalculateTotals();

            // Create Allocation Record
            $allocation = CustomerPaymentAllocation::create([
                'company_id' => $companyId,
                'customer_payment_id' => $advance->id,
                'customer_invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'allocated_at' => now()->toDateString(),
                'allocated_by' => $user?->id,
            ]);

            $this->auditLog->log(
                action: 'advance_allocated_to_invoice',
                model: $allocation,
                oldValues: [],
                newValues: [
                    'advance_id' => $advance->id,
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => $amount,
                ],
                description: "Advance [{$advance->receipt_number}] allocated to invoice [{$invoice->invoice_number}]"
            );

            return $allocation;
        });
    }
}
