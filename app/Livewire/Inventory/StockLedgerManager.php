<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryItem;
use App\Models\StockLedgerEntry;
use App\Models\Warehouse;
use App\Services\TenantContextService;
use Livewire\Component;
use Livewire\WithPagination;

class StockLedgerManager extends Component
{
    use WithPagination;

    public ?int $warehouseFilter = null;
    public ?int $itemFilter = null;
    public string $typeFilter = '';
    public ?string $startDate = null;
    public ?string $endDate = null;

    public function resetFilters(): void
    {
        $this->warehouseFilter = null;
        $this->itemFilter = null;
        $this->typeFilter = '';
        $this->startDate = null;
        $this->endDate = null;
        $this->resetPage();
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $warehouses = Warehouse::orderBy('name')->get();
        $items = InventoryItem::orderBy('name')->get();

        $query = StockLedgerEntry::with(['warehouse', 'item.primaryUom', 'user']);

        if ($this->warehouseFilter) {
            $query->where('warehouse_id', $this->warehouseFilter);
        }

        if ($this->itemFilter) {
            $query->where('inventory_item_id', $this->itemFilter);
        }

        if ($this->typeFilter) {
            $query->where('transaction_type', $this->typeFilter);
        }

        if ($this->startDate) {
            $query->whereDate('transaction_date', '>=', $this->startDate);
        }

        if ($this->endDate) {
            $query->whereDate('transaction_date', '<=', $this->endDate);
        }

        $entries = $query->latest('id')->paginate(20);

        return view('livewire.inventory.stock-ledger-manager', [
            'entries' => $entries,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }
}

