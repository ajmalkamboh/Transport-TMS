<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\GoodsReceiptNote;
use App\Models\GoodsReceiptNoteItem;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseRequisitionItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierBillItem;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcurementService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected GeneralLedgerService $generalLedgerService,
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Create a purchase requisition.
     */
    public function createPurchaseRequisition(array $data, array $items, ?User $user = null): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $reqNumber = $data['requisition_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_PURCHASE_REQUISITION,
                $data['branch_id'] ?? null
            );

            $requisition = PurchaseRequisition::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'requisition_number' => $reqNumber,
                'requested_by' => $data['requested_by'] ?? ($user ? $user->id : null),
                'required_date' => $data['required_date'] ?? null,
                'status' => $data['status'] ?? PurchaseRequisition::STATUS_DRAFT,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($items as $item) {
                $qty = (float) $item['quantity_requested'];
                $estCost = (float) ($item['estimated_unit_cost'] ?? 0);
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $requisition->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_requested' => $qty,
                    'estimated_unit_cost' => $estCost,
                    'estimated_total_cost' => round($qty * $estCost, 2),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $requisition;
        });
    }

    /**
     * Approve a purchase requisition.
     */
    public function approvePurchaseRequisition(PurchaseRequisition $requisition, ?User $user = null): PurchaseRequisition
    {
        $requisition->update([
            'status' => PurchaseRequisition::STATUS_APPROVED,
            'approved_by' => $user?->id,
            'approved_at' => now(),
        ]);

        return $requisition;
    }

    /**
     * Create a Purchase Order.
     */
    public function createPurchaseOrder(array $data, array $items, ?User $user = null): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $poNumber = $data['po_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_PURCHASE_ORDER,
                $data['branch_id'] ?? null
            );

            $subtotal = 0;
            $taxAmount = 0;

            foreach ($items as $item) {
                $lineSubtotal = round((float) $item['quantity_ordered'] * (float) $item['unit_rate'], 2);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }

            $totalAmount = $subtotal + $taxAmount;

            $po = PurchaseOrder::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_requisition_id' => $data['purchase_requisition_id'] ?? null,
                'po_number' => $poNumber,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? 'Net 30 Days',
                'status' => $data['status'] ?? PurchaseOrder::STATUS_DRAFT,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($items as $item) {
                $lineSubtotal = round((float) $item['quantity_ordered'] * (float) $item['unit_rate'], 2);
                $taxRate = (float) ($item['tax_rate'] ?? 0);
                $lineTax = round($lineSubtotal * ($taxRate / 100), 2);

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_ordered' => (float) $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_rate' => (float) $item['unit_rate'],
                    'tax_rate' => $taxRate,
                    'subtotal' => $lineSubtotal,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineSubtotal + $lineTax,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $po;
        });
    }

    /**
     * Approve a Purchase Order.
     */
    public function approvePurchaseOrder(PurchaseOrder $order, ?User $user = null): PurchaseOrder
    {
        $order->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $user?->id,
            'approved_at' => now(),
        ]);

        return $order;
    }

    /**
     * Receive goods via Goods Receipt Note (GRN).
     * Increases inventory in warehouse and recalculates WAC.
     */
    public function recordGoodsReceipt(array $data, array $items, ?User $user = null): GoodsReceiptNote
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $grnNumber = $data['grn_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_GOODS_RECEIPT,
                $data['branch_id'] ?? null
            );

            $warehouseId = $data['warehouse_id'];
            $supplierId = $data['supplier_id'];
            $purchaseOrderId = $data['purchase_order_id'] ?? null;

            $totalReceivedAmount = 0;
            foreach ($items as $item) {
                $totalReceivedAmount += round((float) $item['quantity_received'] * (float) $item['unit_rate'], 2);
            }

            $grn = GoodsReceiptNote::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'warehouse_id' => $warehouseId,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'grn_number' => $grnNumber,
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'supplier_challan_number' => $data['supplier_challan_number'] ?? null,
                'status' => GoodsReceiptNote::STATUS_VERIFIED,
                'total_received_amount' => $totalReceivedAmount,
                'received_by' => $user?->id,
                'remarks' => $data['remarks'] ?? null,
            ]);

            foreach ($items as $item) {
                $itemId = $item['inventory_item_id'];
                $qty = (float) $item['quantity_received'];
                $rate = (float) $item['unit_rate'];
                $lineTotal = round($qty * $rate, 2);

                GoodsReceiptNoteItem::create([
                    'goods_receipt_note_id' => $grn->id,
                    'inventory_item_id' => $itemId,
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'quantity_received' => $qty,
                    'unit_rate' => $rate,
                    'total_amount' => $lineTotal,
                    'batch_or_serial_number' => $item['batch_or_serial_number'] ?? null,
                    'condition_status' => $item['condition_status'] ?? GoodsReceiptNoteItem::CONDITION_GOOD,
                    'notes' => $item['notes'] ?? null,
                ]);

                // 1. Receive into inventory and recalculate WAC
                $this->inventoryService->receiveStock(
                    companyId: $companyId,
                    warehouseId: $warehouseId,
                    itemId: $itemId,
                    quantity: $qty,
                    unitCost: $rate,
                    transactionType: StockLedgerEntry::TYPE_GRN,
                    refType: GoodsReceiptNote::class,
                    refId: $grn->id,
                    refNumber: $grn->grn_number,
                    user: $user,
                    notes: "Received via GRN #{$grn->grn_number}"
                );

                // 2. If tied to a PO item, update quantity_received
                if (!empty($item['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);
                    if ($poItem) {
                        $poItem->increment('quantity_received', $qty);
                    }
                }
            }

            // 3. Update Purchase Order status if linked
            if ($purchaseOrderId) {
                $po = PurchaseOrder::with('items')->find($purchaseOrderId);
                if ($po) {
                    $allReceived = true;
                    $anyReceived = false;
                    foreach ($po->items as $pi) {
                        if ((float) $pi->quantity_received < (float) $pi->quantity_ordered) {
                            $allReceived = false;
                        }
                        if ((float) $pi->quantity_received > 0) {
                            $anyReceived = true;
                        }
                    }

                    if ($allReceived) {
                        $po->update(['status' => PurchaseOrder::STATUS_RECEIVED]);
                    } elseif ($anyReceived) {
                        $po->update(['status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED]);
                    }
                }
            }

            return $grn;
        });
    }

    /**
     * Create Supplier Bill / Invoice.
     * Posts Double-Entry General Ledger:
     * Dr. 1300-SPARES-INVENTORY (Asset)
     * Cr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS (Liability)
     */
    public function createSupplierBill(array $data, array $items, ?User $user = null): SupplierBill
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $billNumber = $data['bill_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_SUPPLIER_BILL,
                $data['branch_id'] ?? null
            );

            $supplierId = $data['supplier_id'];
            $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);

            $subtotal = 0;
            $taxAmount = 0;
            foreach ($items as $item) {
                $lineSub = round((float) $item['quantity'] * (float) $item['unit_rate'], 2);
                $lineTax = (float) ($item['tax_amount'] ?? 0);
                $subtotal += $lineSub;
                $taxAmount += $lineTax;
            }
            $totalAmount = $subtotal + $taxAmount;

            $bill = SupplierBill::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'supplier_id' => $supplierId,
                'goods_receipt_note_id' => $data['goods_receipt_note_id'] ?? null,
                'bill_number' => $billNumber,
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays($supplier->payment_terms_days)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'balance_due' => $totalAmount,
                'status' => SupplierBill::STATUS_UNPAID,
                'created_by' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                $lineSub = round((float) $item['quantity'] * (float) $item['unit_rate'], 2);
                $lineTax = (float) ($item['tax_amount'] ?? 0);
                SupplierBillItem::create([
                    'supplier_bill_id' => $bill->id,
                    'inventory_item_id' => $item['inventory_item_id'] ?? null,
                    'description' => $item['description'] ?? 'Inventory Supplies',
                    'quantity' => (float) $item['quantity'],
                    'unit_rate' => (float) $item['unit_rate'],
                    'subtotal' => $lineSub,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineSub + $lineTax,
                ]);
            }

            // Update supplier balance
            $supplier->increment('current_balance', $totalAmount);

            // Double-Entry Accounting Posting:
            // Dr. 1300-SPARES-INVENTORY (Asset)
            // Cr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS (Liability)
            $inventoryAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
                ->first();

            $payableAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', ChartOfAccount::CODE_AP_TRADE_SUPPLIERS)
                ->first();

            if ($inventoryAccount && $payableAccount) {
                $jv = $this->generalLedgerService->postVoucher([
                    'company_id' => $companyId,
                    'branch_id' => $bill->branch_id,
                    'voucher_type' => 'journal',
                    'voucher_date' => $bill->bill_date->toDateString(),
                    'reference_number' => $bill->bill_number,
                    'description' => "Supplier Bill #{$bill->bill_number} from {$supplier->name}",
                    'idempotency_key' => "SUP-BILL-{$bill->id}",
                    'items' => [
                        [
                            'chart_of_account_id' => $inventoryAccount->id,
                            'debit' => $totalAmount,
                            'credit' => 0,
                            'narration' => "Inventory spare parts received on bill {$bill->bill_number}",
                        ],
                        [
                            'chart_of_account_id' => $payableAccount->id,
                            'debit' => 0,
                            'credit' => $totalAmount,
                            'narration' => "Accounts Payable to supplier {$supplier->name}",
                            'tracking_type' => 'Supplier',
                            'tracking_id' => $supplier->id,
                        ],
                    ],
                ], $user);

                $bill->update(['journal_voucher_id' => $jv->id]);
            }

            return $bill;
        });
    }

    /**
     * Record a Purchase Return to Supplier.
     * Decrements inventory and posts reversing double-entry:
     * Dr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS (Liability)
     * Cr. 1300-SPARES-INVENTORY (Asset)
     */
    public function recordPurchaseReturn(array $data, array $items, ?User $user = null): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $items, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $returnNumber = $data['return_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_PURCHASE_RETURN,
                $data['branch_id'] ?? null
            );

            $supplierId = $data['supplier_id'];
            $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);
            $warehouseId = $data['warehouse_id'];

            $subtotal = 0;
            $taxAmount = 0;
            foreach ($items as $item) {
                $lineSub = round((float) $item['quantity_returned'] * (float) $item['unit_rate'], 2);
                $lineTax = (float) ($item['tax_amount'] ?? 0);
                $subtotal += $lineSub;
                $taxAmount += $lineTax;
            }
            $totalAmount = $subtotal + $taxAmount;

            $purchaseReturn = PurchaseReturn::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'goods_receipt_note_id' => $data['goods_receipt_note_id'] ?? null,
                'return_number' => $returnNumber,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'reason' => $data['reason'] ?? 'Defective / Damaged Goods Returned',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => PurchaseReturn::STATUS_COMPLETED,
                'approved_by' => $user?->id,
            ]);

            foreach ($items as $item) {
                $itemId = $item['inventory_item_id'];
                $qty = (float) $item['quantity_returned'];
                $rate = (float) $item['unit_rate'];
                $lineSub = round($qty * $rate, 2);

                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'inventory_item_id' => $itemId,
                    'quantity_returned' => $qty,
                    'unit_rate' => $rate,
                    'subtotal' => $lineSub,
                    'tax_amount' => (float) ($item['tax_amount'] ?? 0),
                    'total_amount' => $lineSub + (float) ($item['tax_amount'] ?? 0),
                    'reason' => $item['reason'] ?? null,
                ]);

                // Issue stock out of warehouse back to supplier
                $this->inventoryService->issueStock(
                    companyId: $companyId,
                    warehouseId: $warehouseId,
                    itemId: $itemId,
                    quantity: $qty,
                    transactionType: StockLedgerEntry::TYPE_PURCHASE_RETURN,
                    refType: PurchaseReturn::class,
                    refId: $purchaseReturn->id,
                    refNumber: $purchaseReturn->return_number,
                    user: $user,
                    notes: "Purchase return to {$supplier->name} (#{$purchaseReturn->return_number})"
                );
            }

            // Decrement supplier payable balance
            $supplier->decrement('current_balance', $totalAmount);

            // Double-Entry Accounting Posting:
            // Dr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS
            // Cr. 1300-SPARES-INVENTORY
            $inventoryAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
                ->first();

            $payableAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', ChartOfAccount::CODE_AP_TRADE_SUPPLIERS)
                ->first();

            if ($inventoryAccount && $payableAccount) {
                $jv = $this->generalLedgerService->postVoucher([
                    'company_id' => $companyId,
                    'branch_id' => $purchaseReturn->branch_id,
                    'voucher_type' => 'journal',
                    'voucher_date' => $purchaseReturn->return_date->toDateString(),
                    'reference_number' => $purchaseReturn->return_number,
                    'description' => "Purchase Return / Debit Note #{$purchaseReturn->return_number} to {$supplier->name}",
                    'idempotency_key' => "PRT-{$purchaseReturn->id}",
                    'items' => [
                        [
                            'chart_of_account_id' => $payableAccount->id,
                            'debit' => $totalAmount,
                            'credit' => 0,
                            'narration' => "Debit note against returned inventory items to {$supplier->name}",
                            'tracking_type' => 'Supplier',
                            'tracking_id' => $supplier->id,
                        ],
                        [
                            'chart_of_account_id' => $inventoryAccount->id,
                            'debit' => 0,
                            'credit' => $totalAmount,
                            'narration' => "Inventory reduced via purchase return {$purchaseReturn->return_number}",
                        ],
                    ],
                ], $user);

                $purchaseReturn->update(['journal_voucher_id' => $jv->id]);
            }

            return $purchaseReturn;
        });
    }

    /**
     * Record a Supplier Payment.
     * Decrements supplier balance and posts:
     * Dr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS
     * Cr. 1010-CASH / 1020-BANK
     */
    public function recordSupplierPayment(array $data, ?User $user = null): SupplierPayment
    {
        return DB::transaction(function () use ($data, $user) {
            $companyId = $data['company_id'] ?? $this->tenantContext->getCompanyId();

            $paymentNumber = $data['payment_number'] ?? $this->sequenceService->getNextDocumentNumber(
                $companyId,
                DocumentSequence::TYPE_SUPPLIER_PAYMENT,
                $data['branch_id'] ?? null
            );

            $supplierId = $data['supplier_id'];
            $supplier = Supplier::withoutGlobalScopes()->findOrFail($supplierId);
            $amount = (float) $data['amount'];

            $billId = $data['supplier_bill_id'] ?? null;
            $bill = $billId ? SupplierBill::find($billId) : null;

            $payment = SupplierPayment::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'supplier_id' => $supplierId,
                'supplier_bill_id' => $billId,
                'payment_number' => $paymentNumber,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? SupplierPayment::METHOD_BANK_TRANSFER,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            // Update bill if specified
            if ($bill) {
                $newPaid = (float) $bill->paid_amount + $amount;
                $newBal = max(0, (float) $bill->total_amount - $newPaid);
                $status = $newBal <= 0 ? SupplierBill::STATUS_PAID : SupplierBill::STATUS_PARTIALLY_PAID;

                $bill->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBal,
                    'status' => $status,
                ]);
            }

            // Decrement supplier balance
            $supplier->decrement('current_balance', $amount);

            // Double-Entry Accounting Posting:
            // Dr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS
            // Cr. 1010-CASH or 1020-BANK
            $payableAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', ChartOfAccount::CODE_AP_TRADE_SUPPLIERS)
                ->first();

            $creditAccount = null;
            if ($payment->payment_method === SupplierPayment::METHOD_CASH) {
                $creditAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('account_code', ChartOfAccount::CODE_CASH_IN_HAND)
                    ->first();
            } else {
                if ($payment->bank_account_id) {
                    $bank = BankAccount::find($payment->bank_account_id);
                    if ($bank && $bank->chart_of_account_id) {
                        $creditAccount = ChartOfAccount::find($bank->chart_of_account_id);
                    }
                }
                if (!$creditAccount) {
                    $creditAccount = ChartOfAccount::withoutGlobalScopes()
                        ->where('company_id', $companyId)
                        ->where('account_code', ChartOfAccount::CODE_BANK_ACCOUNTS)
                        ->first();
                }
            }

            if ($payableAccount && $creditAccount) {
                $jv = $this->generalLedgerService->postVoucher([
                    'company_id' => $companyId,
                    'branch_id' => $payment->branch_id,
                    'voucher_type' => $payment->payment_method === SupplierPayment::METHOD_CASH ? 'cash_payment' : 'bank_payment',
                    'voucher_date' => $payment->payment_date->toDateString(),
                    'reference_number' => $payment->payment_number,
                    'description' => "Payment of PKR " . number_format($amount, 2) . " to supplier {$supplier->name}",
                    'idempotency_key' => "SPAY-{$payment->id}",
                    'items' => [
                        [
                            'chart_of_account_id' => $payableAccount->id,
                            'debit' => $amount,
                            'credit' => 0,
                            'narration' => "Payment made to supplier {$supplier->name}",
                            'tracking_type' => 'Supplier',
                            'tracking_id' => $supplier->id,
                        ],
                        [
                            'chart_of_account_id' => $creditAccount->id,
                            'debit' => 0,
                            'credit' => $amount,
                            'narration' => "Disbursement via " . ucfirst(str_replace('_', ' ', $payment->payment_method)),
                        ],
                    ],
                ], $user);

                $payment->update(['journal_voucher_id' => $jv->id]);
            }

            return $payment;
        });
    }
}

