<?php

namespace App\Livewire\Settings;

use App\Models\Company;
use App\Models\MasterData;
use App\Services\AuditLogService;
use App\Services\MasterDataService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class MasterDataManager extends Component
{
    public string $selectedCategory = MasterData::CATEGORY_VEHICLE_CATEGORY;
    public bool $showCreateModal = false;

    // Form fields
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $meta_symbol = '';

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('manage_settings')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage master data.');
        }
    }

    public function selectCategory(string $category): void
    {
        if (array_key_exists($category, MasterData::ALL_CATEGORIES)) {
            $this->selectedCategory = $category;
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $items = MasterData::query()
            ->category($this->selectedCategory)
            ->forCompany($companyId)
            ->orderBy('is_system')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.settings.master-data-manager', [
            'company' => $company,
            'items' => $items,
            'categories' => MasterData::ALL_CATEGORIES,
        ]);
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->meta_symbol = '';
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function saveCustomItem(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;

        $validated = $this->validate([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'meta_symbol' => ['nullable', 'string', 'max:20'],
        ]);

        $meta = !empty($validated['meta_symbol']) ? ['symbol' => $validated['meta_symbol']] : null;

        $item = app(MasterDataService::class)->createTenantCustomItem(
            $companyId,
            $this->selectedCategory,
            $validated['code'],
            $validated['name'],
            $validated['description'],
            $meta
        );

        app(AuditLogService::class)->log(
            'master_data_created',
            $item,
            null,
            $item->toArray(),
            sprintf('Created custom lookup [%s] in category [%s].', $item->name, $this->selectedCategory)
        );

        $this->showCreateModal = false;
        session()->flash('success', sprintf('Custom lookup [%s] created successfully.', $item->name));
    }

    public function toggleItemStatus(int $itemId): void
    {
        $this->checkAuthorization();

        /** @var MasterData $item */
        $item = MasterData::withoutGlobalScopes()->findOrFail($itemId);

        // Security check: cannot modify another tenant's custom item or system item
        if ($item->is_system) {
            session()->flash('error', 'System-standard master records cannot be modified.');
            return;
        }

        app(TenantAuthorizationService::class)->validateTenantOwnership($item);

        $item->is_active = !$item->is_active;
        $item->save();

        session()->flash('success', sprintf('Lookup [%s] is now %s.', $item->name, $item->is_active ? 'Active' : 'Inactive'));
    }

    public function deleteCustomItem(int $itemId): void
    {
        $this->checkAuthorization();

        /** @var MasterData $item */
        $item = MasterData::withoutGlobalScopes()->findOrFail($itemId);

        if ($item->is_system) {
            session()->flash('error', 'System standard records cannot be deleted.');
            return;
        }

        app(TenantAuthorizationService::class)->validateTenantOwnership($item);

        app(AuditLogService::class)->log('master_data_deleted', $item, $item->toArray(), null, 'Custom lookup deleted.');
        $item->delete();

        session()->flash('success', 'Custom lookup removed.');
    }
}

