<?php

namespace App\Livewire\Inventory;

use App\Models\Branch;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\Warehouse;
use App\Services\ProcurementService;
use App\Services\TenantContextService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ProcurementManager extends Component
{
    use WithPagination;

    public string $activeTab = 'orders'; // orders, grn, bills, suppliers, requisitions, returns, payments

    // Supplier Modal
    public bool $showSupplierModal = false;
    public ?int $selectedSupplierId = null;
    public string $supplier_name = '';
    public string $supplier_code = '';
    public string $supplier_contact_name = '';
    public string $supplier_phone = '';
    public string $supplier_email = '';
    public string $supplier_address = '';
    public string $supplier_tax_number = '';
    public int $supplier_payment_terms_days = 30;
    public float $supplier_credit_limit = 0;

    // PO Modal
    public bool $showPoModal = false;
    public ?int $po_supplier_id = null;
    public ?int $po_warehouse_id = null;
    public ?int $po_branch_id = null;
    public string $po_order_date = '';
    public ?string $po_expected_delivery_date = null;
    public string $po_notes = '';
    public array $po_items = []; // [['inventory_item_id' => ..., 'quantity_ordered' => ..., 'unit_rate' => ..., 'tax_rate' => ...]]

    // GRN Modal
    public bool $showGrnModal = false;
    public ?int $grn_po_id = null;
    public ?int $grn_supplier_id = null;
    public ?int $grn_warehouse_id = null;
    public string $grn_challan_number = '';
    public array $grn_items = []; // [['inventory_item_id' => ..., 'quantity_received' => ..., 'unit_rate' => ..., 'purchase_order_item_id' => ...]]

    // Supplier Bill Modal
    public bool $showBillModal = false;
    public ?int $bill_supplier_id = null;
    public ?int $bill_grn_id = null;
    public string $bill_number = '';
    public string $bill_date = '';
    public string $bill_due_date = '';
    public array $bill_items = []; // [['inventory_item_id' => ..., 'description' => ..., 'quantity' => ..., 'unit_rate' => ..., 'tax_amount' => ...]]

    // Supplier Payment Modal
    public bool $showPaymentModal = false;
    public ?int $payment_supplier_id = null;
    public ?int $payment_bill_id = null;
    public float $payment_amount = 0.0;
    public string $payment_method = 'bank_transfer';
    public string $payment_date = '';
    public string $payment_reference = '';
    public string $payment_notes = '';

    public function mount(): void
    {
        $this->po_order_date = now()->toDateString();
        $this->bill_date = now()->toDateString();
        $this->bill_due_date = now()->addDays(30)->toDateString();
        $this->payment_date = now()->toDateString();
        $this->addPoItemRow();
    }

    public function addPoItemRow(): void
    {
        $this->po_items[] = [
            'inventory_item_id' => null,
            'quantity_ordered' => 1,
            'unit_rate' => 0,
            'tax_rate' => 0,
        ];
    }

    public function removePoItemRow(int $index): void
    {
        unset($this->po_items[$index]);
        $this->po_items = array_values($this->po_items);
    }

    public function openCreateSupplierModal(): void
    {
        $this->resetSupplierForm();
        $this->showSupplierModal = true;
    }

    public function saveSupplier(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'supplier_name' => 'required|string|max:150',
            'supplier_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->where('company_id', $companyId)->ignore($this->selectedSupplierId),
            ],
            'supplier_phone' => 'nullable|string|max:50',
            'supplier_email' => 'nullable|email|max:100',
            'supplier_payment_terms_days' => 'required|integer|min:0',
        ]);

        Supplier::create([
            'company_id' => $companyId,
            'name' => trim($this->supplier_name),
            'code' => strtoupper(trim($this->supplier_code)),
            'contact_name' => trim($this->supplier_contact_name) ?: null,
            'phone' => trim($this->supplier_phone) ?: null,
            'email' => trim($this->supplier_email) ?: null,
            'address' => trim($this->supplier_address) ?: null,
            'tax_number' => trim($this->supplier_tax_number) ?: null,
            'payment_terms_days' => $this->supplier_payment_terms_days,
            'credit_limit' => $this->supplier_credit_limit,
            'is_active' => true,
        ]);

        $this->showSupplierModal = false;
        $this->resetSupplierForm();
        session()->flash('message', 'Trade supplier registered successfully.');
    }

    public function savePurchaseOrder(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'po_supplier_id' => 'required|exists:suppliers,id',
            'po_warehouse_id' => 'required|exists:warehouses,id',
            'po_order_date' => 'required|date',
            'po_items' => 'required|array|min:1',
            'po_items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'po_items.*.quantity_ordered' => 'required|numeric|gt:0',
            'po_items.*.unit_rate' => 'required|numeric|gt:0',
        ]);

        $procService = app(ProcurementService::class);

        $po = $procService->createPurchaseOrder(
            [
                'company_id' => $companyId,
                'supplier_id' => $this->po_supplier_id,
                'warehouse_id' => $this->po_warehouse_id,
                'branch_id' => $this->po_branch_id,
                'order_date' => $this->po_order_date,
                'expected_delivery_date' => $this->po_expected_delivery_date,
                'notes' => $this->po_notes ?: null,
            ],
            $this->po_items,
            auth()->user()
        );

        $this->showPoModal = false;
        $this->resetPoForm();
        session()->flash('message', "Purchase Order [{$po->po_number}] created successfully.");
    }

    public function approveOrder(int $id): void
    {
        $order = PurchaseOrder::findOrFail($id);
        app(ProcurementService::class)->approvePurchaseOrder($order, auth()->user());
        session()->flash('message', "Purchase Order [{$order->po_number}] approved.");
    }

    public function openReceivePoModal(int $poId): void
    {
        $po = PurchaseOrder::with('items.item')->findOrFail($poId);
        $this->grn_po_id = $po->id;
        $this->grn_supplier_id = $po->supplier_id;
        $this->grn_warehouse_id = $po->warehouse_id;
        $this->grn_challan_number = '';

        $this->grn_items = [];
        foreach ($po->items as $pi) {
            $rem = (float) $pi->remaining_quantity;
            if ($rem > 0) {
                $this->grn_items[] = [
                    'inventory_item_id' => $pi->inventory_item_id,
                    'purchase_order_item_id' => $pi->id,
                    'item_name' => $pi->item?->name,
                    'quantity_received' => $rem,
                    'unit_rate' => (float) $pi->unit_rate,
                ];
            }
        }

        $this->showGrnModal = true;
    }

    public function saveGoodsReceipt(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'grn_warehouse_id' => 'required|exists:warehouses,id',
            'grn_supplier_id' => 'required|exists:suppliers,id',
            'grn_items' => 'required|array|min:1',
            'grn_items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'grn_items.*.quantity_received' => 'required|numeric|gt:0',
            'grn_items.*.unit_rate' => 'required|numeric|gt:0',
        ]);

        $procService = app(ProcurementService::class);

        $grn = $procService->recordGoodsReceipt(
            [
                'company_id' => $companyId,
                'warehouse_id' => $this->grn_warehouse_id,
                'supplier_id' => $this->grn_supplier_id,
                'purchase_order_id' => $this->grn_po_id,
                'supplier_challan_number' => $this->grn_challan_number ?: null,
                'received_date' => now()->toDateString(),
            ],
            $this->grn_items,
            auth()->user()
        );

        $this->showGrnModal = false;
        session()->flash('message', "Goods Receipt Note [{$grn->grn_number}] verified & stock added to warehouse.");
    }

    public function openBillModalForGrn(int $grnId): void
    {
        $grn = GoodsReceiptNote::with(['items.item', 'supplier']).findOrFail($grnId);
        $this->bill_grn_id = $grn->id;
        $this->bill_supplier_id = $grn->supplier_id;
        $this->bill_number = 'BILL-' . $grn->grn_number;
        $this->bill_date = now()->toDateString();
        $this->bill_due_date = now()->addDays($grn->supplier->payment_terms_days)->toDateString();

        $this->bill_items = [];
        foreach ($grn->items as $gi) {
            $this->bill_items[] = [
                'inventory_item_id' => $gi->inventory_item_id,
                'description' => $gi->item?->name ?? 'Spare Parts',
                'quantity' => (float) $gi->quantity_received,
                'unit_rate' => (float) $gi->unit_rate,
                'tax_amount' => 0,
            ];
        }

        $this->showBillModal = true;
    }

    public function saveSupplierBill(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'bill_supplier_id' => 'required|exists:suppliers,id',
            'bill_number' => 'required|string|max:100',
            'bill_date' => 'required|date',
            'bill_due_date' => 'required|date',
            'bill_items' => 'required|array|min:1',
            'bill_items.*.quantity' => 'required|numeric|gt:0',
            'bill_items.*.unit_rate' => 'required|numeric|gt:0',
        ]);

        $procService = app(ProcurementService::class);

        $bill = $procService->createSupplierBill(
            [
                'company_id' => $companyId,
                'supplier_id' => $this->bill_supplier_id,
                'goods_receipt_note_id' => $this->bill_grn_id,
                'bill_number' => $this->bill_number,
                'bill_date' => $this->bill_date,
                'due_date' => $this->bill_due_date,
            ],
            $this->bill_items,
            auth()->user()
        );

        $this->showBillModal = false;
        session()->flash('message', "Supplier Bill [{$bill->bill_number}] posted to General Ledger (Dr. 1300 Spares Inventory / Cr. 2110 AP Suppliers).");
    }

    public function openPaymentModal(int $billId): void
    {
        $bill = SupplierBill::with('supplier')->findOrFail($billId);
        $this->payment_bill_id = $bill->id;
        $this->payment_supplier_id = $bill->supplier_id;
        $this->payment_amount = (float) $bill->balance_due;
        $this->payment_date = now()->toDateString();
        $this->payment_method = 'bank_transfer';
        $this->payment_reference = '';
        $this->payment_notes = "Payment against Bill #{$bill->bill_number}";

        $this->showPaymentModal = true;
    }

    public function saveSupplierPayment(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'payment_supplier_id' => 'required|exists:suppliers,id',
            'payment_amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,bank_transfer,cheque',
            'payment_date' => 'required|date',
        ]);

        $procService = app(ProcurementService::class);

        $payment = $procService->recordSupplierPayment(
            [
                'company_id' => $companyId,
                'supplier_id' => $this->payment_supplier_id,
                'supplier_bill_id' => $this->payment_bill_id,
                'amount' => $this->payment_amount,
                'payment_method' => $this->payment_method,
                'payment_date' => $this->payment_date,
                'reference_number' => $this->payment_reference,
                'notes' => $this->payment_notes,
            ],
            auth()->user()
        );

        $this->showPaymentModal = false;
        session()->flash('message', "Supplier payment [{$payment->payment_number}] registered and posted (Dr. 2110 AP / Cr. Bank).");
    }

    private function resetSupplierForm(): void
    {
        $this->selectedSupplierId = null;
        $this->supplier_name = '';
        $this->supplier_code = '';
        $this->supplier_contact_name = '';
        $this->supplier_phone = '';
        $this->supplier_email = '';
        $this->supplier_address = '';
        $this->supplier_tax_number = '';
        $this->supplier_payment_terms_days = 30;
        $this->supplier_credit_limit = 0;
    }

    private function resetPoForm(): void
    {
        $this->po_supplier_id = null;
        $this->po_warehouse_id = null;
        $this->po_branch_id = null;
        $this->po_expected_delivery_date = null;
        $this->po_notes = '';
        $this->po_items = [];
        $this->addPoItemRow();
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get();

        $purchaseOrders = PurchaseOrder::with(['supplier', 'warehouse', 'items.item'])->latest()->paginate(10);
        $grns = GoodsReceiptNote::with(['supplier', 'warehouse', 'purchaseOrder', 'items.item'])->latest()->paginate(10);
        $bills = SupplierBill::with(['supplier', 'goodsReceiptNote'])->latest()->paginate(10);
        $payments = SupplierPayment::with(['supplier', 'bill'])->latest()->paginate(10);

        return view('livewire.inventory.procurement-manager', [
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'items' => $items,
            'purchaseOrders' => $purchaseOrders,
            'grns' => $grns,
            'bills' => $bills,
            'payments' => $payments,
        ]);
    }
}

