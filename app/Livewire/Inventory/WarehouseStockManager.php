<?php

namespace App\Livewire\Inventory;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\DocumentSequenceService;
use App\Services\InventoryService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class WarehouseStockManager extends Component
{
    use WithPagination;

    // Filters & Tabs
    public string $activeTab = 'stock'; // stock, warehouses, transfers, adjustments
    public ?int $selectedWarehouseId = null;
    public string $search = '';

    // Warehouse Modal
    public bool $showWarehouseModal = false;
    public bool $isEditingWarehouse = false;
    public ?int $editingWarehouseId = null;
    public string $warehouse_code = '';
    public string $warehouse_name = '';
    public ?int $warehouse_branch_id = null;
    public string $warehouse_address = '';
    public string $warehouse_contact_person = '';
    public string $warehouse_phone = '';
    public bool $warehouse_is_default = false;

    // Stock Transfer Modal
    public bool $showTransferModal = false;
    public ?int $transfer_from_warehouse_id = null;
    public ?int $transfer_to_warehouse_id = null;
    public ?int $transfer_item_id = null;
    public float $transfer_quantity = 1.0;
    public string $transfer_notes = '';

    // Stock Adjustment Modal
    public bool $showAdjustmentModal = false;
    public ?int $adjustment_warehouse_id = null;
    public string $adjustment_type = StockAdjustment::TYPE_PHYSICAL_COUNT; // gain, loss, damage, wastage, physical_count
    public ?int $adjustment_item_id = null;
    public float $adjustment_counted_quantity = 0.0;
    public string $adjustment_reason = '';

    public function openCreateWarehouseModal(): void
    {
        $this->resetWarehouseForm();
        $this->isEditingWarehouse = false;
        $this->showWarehouseModal = true;
    }

    public function editWarehouse(int $id): void
    {
        $wh = Warehouse::findOrFail($id);
        $this->editingWarehouseId = $wh->id;
        $this->warehouse_code = $wh->code;
        $this->warehouse_name = $wh->name;
        $this->warehouse_branch_id = $wh->branch_id;
        $this->warehouse_address = $wh->address ?? '';
        $this->warehouse_contact_person = $wh->contact_person ?? '';
        $this->warehouse_phone = $wh->phone ?? '';
        $this->warehouse_is_default = (bool) $wh->is_default;

        $this->isEditingWarehouse = true;
        $this->showWarehouseModal = true;
    }

    public function saveWarehouse(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'warehouse_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($this->editingWarehouseId),
            ],
            'warehouse_name' => 'required|string|max:150',
            'warehouse_branch_id' => 'nullable|exists:branches,id',
            'warehouse_address' => 'nullable|string',
            'warehouse_contact_person' => 'nullable|string|max:100',
            'warehouse_phone' => 'nullable|string|max:50',
        ]);

        $data = [
            'company_id' => $companyId,
            'branch_id' => $this->warehouse_branch_id ?: null,
            'code' => strtoupper(trim($this->warehouse_code)),
            'name' => trim($this->warehouse_name),
            'address' => $this->warehouse_address ?: null,
            'contact_person' => $this->warehouse_contact_person ?: null,
            'phone' => $this->warehouse_phone ?: null,
            'is_default' => $this->warehouse_is_default,
            'is_active' => true,
        ];

        if ($this->isEditingWarehouse && $this->editingWarehouseId) {
            $wh = Warehouse::findOrFail($this->editingWarehouseId);
            $wh->update($data);
            session()->flash('message', "Warehouse [{$wh->name}] updated successfully.");
        } else {
            $wh = Warehouse::create($data);
            session()->flash('message', "Warehouse [{$wh->name}] created successfully.");
        }

        $this->showWarehouseModal = false;
        $this->resetWarehouseForm();
    }

    public function executeTransfer(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'transfer_from_warehouse_id' => 'required|different:transfer_to_warehouse_id|exists:warehouses,id',
            'transfer_to_warehouse_id' => 'required|exists:warehouses,id',
            'transfer_item_id' => 'required|exists:inventory_items,id',
            'transfer_quantity' => 'required|numeric|gt:0',
        ]);

        $invService = app(InventoryService::class);
        $seqService = app(DocumentSequenceService::class);

        try {
            DB::transaction(function () use ($companyId, $invService, $seqService) {
                $transferNumber = $seqService->getNextDocumentNumber($companyId, DocumentSequence::TYPE_STOCK_TRANSFER);

                $transfer = StockTransfer::create([
                    'company_id' => $companyId,
                    'transfer_number' => $transferNumber,
                    'from_warehouse_id' => $this->transfer_from_warehouse_id,
                    'to_warehouse_id' => $this->transfer_to_warehouse_id,
                    'transfer_date' => now()->toDateString(),
                    'status' => StockTransfer::STATUS_DRAFT,
                    'total_cost' => 0,
                    'notes' => $this->transfer_notes ?: null,
                ]);

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'inventory_item_id' => $this->transfer_item_id,
                    'quantity_transferred' => $this->transfer_quantity,
                    'quantity_received' => 0,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                ]);

                $invService->transferStock($transfer, auth()->user());
            });

            session()->flash('message', "Stock transfer completed successfully.");
            $this->showTransferModal = false;
            $this->resetTransferForm();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function executeAdjustment(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'adjustment_warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_type' => 'required|in:gain,loss,damage,wastage,physical_count',
            'adjustment_item_id' => 'required|exists:inventory_items,id',
            'adjustment_counted_quantity' => 'required|numeric|min:0',
            'adjustment_reason' => 'required|string|max:255',
        ]);

        $invService = app(InventoryService::class);
        $seqService = app(DocumentSequenceService::class);

        try {
            DB::transaction(function () use ($companyId, $invService, $seqService) {
                $adjNumber = $seqService->getNextDocumentNumber($companyId, DocumentSequence::TYPE_STOCK_ADJUSTMENT);

                $stock = $invService->getOrCreateWarehouseStock(
                    $companyId,
                    $this->adjustment_warehouse_id,
                    $this->adjustment_item_id
                );

                $currentQty = (float) $stock->quantity_on_hand;
                $diff = (float) $this->adjustment_counted_quantity - $currentQty;

                $adjustment = StockAdjustment::create([
                    'company_id' => $companyId,
                    'warehouse_id' => $this->adjustment_warehouse_id,
                    'adjustment_number' => $adjNumber,
                    'adjustment_date' => now()->toDateString(),
                    'adjustment_type' => $this->adjustment_type,
                    'reason' => $this->adjustment_reason,
                    'total_cost' => 0,
                    'status' => StockAdjustment::STATUS_DRAFT,
                    'created_by' => auth()->id(),
                ]);

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'inventory_item_id' => $this->adjustment_item_id,
                    'system_quantity' => $currentQty,
                    'counted_quantity' => $this->adjustment_counted_quantity,
                    'quantity_difference' => $diff,
                    'unit_cost' => (float) $stock->average_unit_cost,
                    'total_difference_cost' => round($diff * (float) $stock->average_unit_cost, 2),
                    'notes' => $this->adjustment_reason,
                ]);

                $invService->adjustStock($adjustment, auth()->user());
            });

            session()->flash('message', "Stock adjustment registered and posted successfully.");
            $this->showAdjustmentModal = false;
            $this->resetAdjustmentForm();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function resetWarehouseForm(): void
    {
        $this->editingWarehouseId = null;
        $this->warehouse_code = '';
        $this->warehouse_name = '';
        $this->warehouse_branch_id = null;
        $this->warehouse_address = '';
        $this->warehouse_contact_person = '';
        $this->warehouse_phone = '';
        $this->warehouse_is_default = false;
    }

    private function resetTransferForm(): void
    {
        $this->transfer_from_warehouse_id = null;
        $this->transfer_to_warehouse_id = null;
        $this->transfer_item_id = null;
        $this->transfer_quantity = 1.0;
        $this->transfer_notes = '';
    }

    private function resetAdjustmentForm(): void
    {
        $this->adjustment_warehouse_id = null;
        $this->adjustment_item_id = null;
        $this->adjustment_counted_quantity = 0.0;
        $this->adjustment_reason = '';
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $warehouses = Warehouse::with('branch')->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();
        $items = InventoryItem::where('is_active', true)->orderBy('name')->get();

        $stockQuery = WarehouseStock::with(['warehouse', 'item.primaryUom', 'item.category']);

        if ($this->selectedWarehouseId) {
            $stockQuery->where('warehouse_id', $this->selectedWarehouseId);
        }

        if ($this->search) {
            $stockQuery->whereHas('item', function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('item_code', 'like', "%{$this->search}%");
            });
        }

        $stocks = $stockQuery->paginate(15);

        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.item'])
            ->latest()
            ->take(20)
            ->get();

        $adjustments = StockAdjustment::with(['warehouse', 'items.item'])
            ->latest()
            ->take(20)
            ->get();

        return view('livewire.inventory.warehouse-stock-manager', [
            'stocks' => $stocks,
            'warehouses' => $warehouses,
            'branches' => $branches,
            'items' => $items,
            'transfers' => $transfers,
            'adjustments' => $adjustments,
        ]);
    }
}

