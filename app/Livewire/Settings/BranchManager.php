<?php

namespace App\Livewire\Settings;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FeatureManagerService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class BranchManager extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $selectedBranchId = null;

    // Form fields
    public string $name = '';
    public string $code = '';
    public string $city = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public ?int $manager_id = null;
    public string $operating_hours = '24/7 Dispatch Operations';
    public int $dock_capacity = 2;
    public bool $is_head_office = false;
    public bool $is_default = false;
    public bool $is_active = true;

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('manage_branches')) {
            throw new AuthorizationException('Access denied: You do not have permission to manage company branches.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $branches = Branch::where('company_id', $companyId)
            ->with(['manager', 'authorizedUsers'])
            ->withCount('authorizedUsers')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('city', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByDesc('is_head_office')
            ->orderBy('name')
            ->paginate(10);

        $companyUsers = User::where('company_id', $companyId)->where('status', 'active')->get();

        return view('livewire.settings.branch-manager', [
            'branches' => $branches,
            'company' => $company,
            'companyUsers' => $companyUsers,
            'canAddBranch' => $company->canAddBranch(),
        ]);
    }

    public function openCreateModal(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        // Enforce Plan Branch Quota limit
        app(FeatureManagerService::class)->checkBranchLimit($company);

        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->selectedBranchId = null;
        $this->name = '';
        $this->code = '';
        $this->city = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->manager_id = null;
        $this->operating_hours = '24/7 Dispatch Operations';
        $this->dock_capacity = 2;
        $this->is_head_office = false;
        $this->is_default = false;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function editBranch(int $branchId): void
    {
        $this->checkAuthorization();

        /** @var Branch $branch */
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($branch);

        $this->selectedBranchId = $branch->id;
        $this->name = $branch->name;
        $this->code = $branch->code;
        $this->city = $branch->city;
        $this->phone = $branch->phone ?? '';
        $this->email = $branch->email ?? '';
        $this->address = $branch->address ?? '';
        $this->manager_id = $branch->manager_id;
        $this->operating_hours = $branch->operating_hours ?? '24/7 Dispatch Operations';
        $this->dock_capacity = (int) ($branch->dock_capacity ?? 2);
        $this->is_head_office = (bool) $branch->is_head_office;
        $this->is_default = (bool) $branch->is_default;
        $this->is_active = (bool) $branch->is_active;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function saveBranch(): void
    {
        $this->checkAuthorization();
        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $company = Company::findOrFail($companyId);

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('branches', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($this->selectedBranchId),
            ],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'operating_hours' => ['nullable', 'string', 'max:100'],
            'dock_capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_head_office' => ['boolean'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        DB::transaction(function () use ($company, $companyId, $validated) {
            // If marked as default or head office, unset previous flags on other branches
            if ($validated['is_default']) {
                Branch::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->update(['is_default' => false]);
            }

            if ($validated['is_head_office']) {
                Branch::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->update(['is_head_office' => false]);
            }

            if ($this->isEditing) {
                /** @var Branch $branch */
                $branch = Branch::withoutGlobalScopes()->findOrFail($this->selectedBranchId);
                app(TenantAuthorizationService::class)->validateTenantOwnership($branch);

                $old = $branch->toArray();
                $branch->update([
                    'name' => $validated['name'],
                    'code' => strtoupper($validated['code']),
                    'city' => $validated['city'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'address' => $validated['address'],
                    'manager_id' => $validated['manager_id'],
                    'operating_hours' => $validated['operating_hours'],
                    'dock_capacity' => $validated['dock_capacity'],
                    'is_head_office' => $validated['is_head_office'],
                    'is_default' => $validated['is_default'],
                    'is_active' => $validated['is_active'],
                ]);

                app(AuditLogService::class)->log('branch_updated', $branch, $old, $branch->toArray(), 'Branch details updated.');
                session()->flash('success', sprintf('Branch [%s] updated successfully.', $branch->name));
            } else {
                // Enforce plan quota limit before creation
                app(FeatureManagerService::class)->checkBranchLimit($company);

                $branch = Branch::create([
                    'company_id' => $companyId,
                    'name' => $validated['name'],
                    'code' => strtoupper($validated['code']),
                    'city' => $validated['city'],
                    'phone' => $validated['phone'],
                    'email' => $validated['email'],
                    'address' => $validated['address'],
                    'manager_id' => $validated['manager_id'],
                    'operating_hours' => $validated['operating_hours'],
                    'dock_capacity' => $validated['dock_capacity'],
                    'is_head_office' => $validated['is_head_office'],
                    'is_default' => $validated['is_default'],
                    'is_active' => $validated['is_active'],
                ]);

                // Authorize assigned manager for this branch
                if ($branch->manager_id) {
                    $manager = User::find($branch->manager_id);
                    $manager?->branches()->syncWithoutDetaching([$branch->id]);
                }

                app(AuditLogService::class)->log('branch_created', $branch, null, $branch->toArray(), 'New branch created.');
                session()->flash('success', sprintf('New branch [%s] created successfully.', $branch->name));
            }
        });

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleBranchStatus(int $branchId): void
    {
        $this->checkAuthorization();

        /** @var Branch $branch */
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($branch);

        if ($branch->is_head_office && $branch->is_active) {
            session()->flash('error', 'Cannot deactivate the company Head Office branch.');
            return;
        }

        $branch->is_active = !$branch->is_active;
        $branch->save();

        session()->flash('success', sprintf('Branch [%s] status changed to [%s].', $branch->name, $branch->is_active ? 'Active' : 'Inactive'));
    }
}
