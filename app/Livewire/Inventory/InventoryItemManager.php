<?php

namespace App\Livewire\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasurement;
use App\Models\UomConversion;
use App\Services\AuditLogService;
use App\Services\TenantContextService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class InventoryItemManager extends Component
{
    use WithPagination;

    // Filters & Tabs
    public string $activeTab = 'items'; // items, categories, uom, conversions
    public string $search = '';
    public ?int $categoryFilter = null;
    public bool $lowStockOnly = false;

    // Item Form Modal
    public bool $showItemModal = false;
    public bool $isEditingItem = false;
    public ?int $selectedItemId = null;

    public string $item_code = '';
    public string $sku = '';
    public string $name = '';
    public ?int $category_id = null;
    public ?int $primary_uom_id = null;
    public ?int $preferred_supplier_id = null;
    public float $reorder_level = 5.0;
    public float $safety_stock = 2.0;
    public float $average_cost = 0.0;
    public string $description = '';
    public bool $is_active = true;

    // Category Modal
    public bool $showCategoryModal = false;
    public string $category_name = '';
    public string $category_code = '';
    public string $category_description = '';

    // UOM Modal
    public bool $showUomModal = false;
    public string $uom_name = '';
    public string $uom_code = '';
    public string $uom_symbol = '';

    // UOM Conversion Modal
    public bool $showConversionModal = false;
    public ?int $from_uom_id = null;
    public ?int $to_uom_id = null;
    public float $conversion_factor = 1.0;

    protected function rules(): array
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        return [
            'item_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_items', 'item_code')
                    ->where('company_id', $companyId)
                    ->ignore($this->selectedItemId),
            ],
            'name' => 'required|string|max:150',
            'primary_uom_id' => 'required|exists:units_of_measurement,id',
            'category_id' => 'nullable|exists:inventory_categories,id',
            'preferred_supplier_id' => 'nullable|exists:suppliers,id',
            'sku' => 'nullable|string|max:100',
            'reorder_level' => 'required|numeric|min:0',
            'safety_stock' => 'required|numeric|min:0',
            'average_cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function openCreateItemModal(): void
    {
        $this->resetItemForm();
        $this->isEditingItem = false;
        $this->showItemModal = true;
    }

    public function editItem(int $id): void
    {
        $item = InventoryItem::findOrFail($id);
        $this->selectedItemId = $item->id;
        $this->item_code = $item->item_code;
        $this->sku = $item->sku ?? '';
        $this->name = $item->name;
        $this->category_id = $item->category_id;
        $this->primary_uom_id = $item->primary_uom_id;
        $this->preferred_supplier_id = $item->preferred_supplier_id;
        $this->reorder_level = (float) $item->reorder_level;
        $this->safety_stock = (float) $item->safety_stock;
        $this->average_cost = (float) $item->average_cost;
        $this->description = $item->description ?? '';
        $this->is_active = (bool) $item->is_active;

        $this->isEditingItem = true;
        $this->showItemModal = true;
    }

    public function saveItem(): void
    {
        $this->validate();

        $companyId = app(TenantContextService::class)->getCompanyId();

        $data = [
            'company_id' => $companyId,
            'item_code' => strtoupper(trim($this->item_code)),
            'sku' => $this->sku ? trim($this->sku) : null,
            'name' => trim($this->name),
            'category_id' => $this->category_id ?: null,
            'primary_uom_id' => $this->primary_uom_id,
            'preferred_supplier_id' => $this->preferred_supplier_id ?: null,
            'reorder_level' => $this->reorder_level,
            'safety_stock' => $this->safety_stock,
            'average_cost' => $this->average_cost,
            'description' => $this->description ?: null,
            'is_active' => $this->is_active,
        ];

        if ($this->isEditingItem && $this->selectedItemId) {
            $item = InventoryItem::findOrFail($this->selectedItemId);
            $item->update($data);
            session()->flash('message', "Item [{$item->item_code}] updated successfully.");
        } else {
            $item = InventoryItem::create($data);
            session()->flash('message', "New Inventory Item [{$item->item_code}] created successfully.");
        }

        $this->showItemModal = false;
        $this->resetItemForm();
    }

    public function saveCategory(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'category_name' => 'required|string|max:100',
            'category_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('inventory_categories', 'code')->where('company_id', $companyId),
            ],
        ]);

        InventoryCategory::create([
            'company_id' => $companyId,
            'name' => trim($this->category_name),
            'code' => strtoupper(trim($this->category_code)),
            'description' => $this->category_description ?: null,
            'is_active' => true,
        ]);

        $this->showCategoryModal = false;
        $this->category_name = '';
        $this->category_code = '';
        $this->category_description = '';
        session()->flash('message', 'Inventory Category created successfully.');
    }

    public function saveUom(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'uom_name' => 'required|string|max:50',
            'uom_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('units_of_measurement', 'code')->where('company_id', $companyId),
            ],
            'uom_symbol' => 'nullable|string|max:20',
        ]);

        UnitOfMeasurement::create([
            'company_id' => $companyId,
            'name' => trim($this->uom_name),
            'code' => strtoupper(trim($this->uom_code)),
            'symbol' => trim($this->uom_symbol) ?: null,
            'is_active' => true,
        ]);

        $this->showUomModal = false;
        $this->uom_name = '';
        $this->uom_code = '';
        $this->uom_symbol = '';
        session()->flash('message', 'Unit of Measurement created successfully.');
    }

    public function saveConversion(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $this->validate([
            'from_uom_id' => 'required|different:to_uom_id|exists:units_of_measurement,id',
            'to_uom_id' => 'required|exists:units_of_measurement,id',
            'conversion_factor' => 'required|numeric|gt:0',
        ]);

        UomConversion::updateOrCreate(
            [
                'company_id' => $companyId,
                'from_uom_id' => $this->from_uom_id,
                'to_uom_id' => $this->to_uom_id,
            ],
            [
                'conversion_factor' => $this->conversion_factor,
            ]
        );

        $this->showConversionModal = false;
        $this->from_uom_id = null;
        $this->to_uom_id = null;
        $this->conversion_factor = 1.0;
        session()->flash('message', 'Unit Conversion rule saved successfully.');
    }

    private function resetItemForm(): void
    {
        $this->selectedItemId = null;
        $this->item_code = '';
        $this->sku = '';
        $this->name = '';
        $this->category_id = null;
        $this->primary_uom_id = null;
        $this->preferred_supplier_id = null;
        $this->reorder_level = 5.0;
        $this->safety_stock = 2.0;
        $this->average_cost = 0.0;
        $this->description = '';
        $this->is_active = true;
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId();

        $query = InventoryItem::with(['category', 'primaryUom', 'preferredSupplier', 'warehouseStocks']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('item_code', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%");
            });
        }

        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        $items = $query->latest()->paginate(15);

        $categories = InventoryCategory::orderBy('name')->get();
        $uoms = UnitOfMeasurement::orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();
        $conversions = UomConversion::with(['fromUom', 'toUom'])->latest()->get();

        return view('livewire.inventory.inventory-item-manager', [
            'items' => $items,
            'categories' => $categories,
            'uoms' => $uoms,
            'suppliers' => $suppliers,
            'conversions' => $conversions,
        ]);
    }
}

