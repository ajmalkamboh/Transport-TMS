<?php

namespace App\Livewire\Saas;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentSequenceService;
use App\Services\FeatureManagerService;
use App\Services\SubscriptionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class TenantManager extends Component
{
    use WithPagination;

    // Search and Filters
    public string $search = '';
    public string $statusFilter = '';

    // Create / Edit Tenant Form State
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $selectedCompanyId = null;

    public string $name = '';
    public string $code = '';
    public string $slug = '';
    public string $email = '';
    public string $phone = '';
    public string $city = '';
    public string $address = '';
    public string $tax_number = '';
    public ?int $plan_id = null;
    public string $billing_cycle = 'monthly';
    public bool $is_trial = false;

    // Initial Owner Credentials (for new tenant)
    public string $owner_name = '';
    public string $owner_email = '';
    public string $owner_phone = '';
    public string $owner_password = '';

    // View Tenant Detail Modal State
    public bool $showDetailModal = false;
    public ?Company $viewingCompany = null;
    public array $usageStats = [];

    protected function checkSuperAdmin(): void
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            throw new AuthorizationException('Access denied: This module requires Platform Super Administrator authority.');
        }
    }

    public function render()
    {
        $this->checkSuperAdmin();

        $companies = Company::with(['plan', 'owner', 'branches'])
            ->withCount(['users', 'branches'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('code', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest()
            ->paginate(10);

        $plans = SubscriptionPlan::where('is_active', true)->get();

        return view('livewire.saas.tenant-manager', [
            'companies' => $companies,
            'plans' => $plans,
            'allFeatures' => SubscriptionPlan::ALL_FEATURES,
        ]);
    }

    public function openCreateModal(): void
    {
        $this->checkSuperAdmin();
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->selectedCompanyId = null;
        $this->name = '';
        $this->code = '';
        $this->slug = '';
        $this->email = '';
        $this->phone = '';
        $this->city = 'Lahore';
        $this->address = '';
        $this->tax_number = '';
        $this->plan_id = SubscriptionPlan::where('is_active', true)->first()?->id;
        $this->billing_cycle = 'monthly';
        $this->is_trial = false;
        $this->owner_name = '';
        $this->owner_email = '';
        $this->owner_phone = '';
        $this->owner_password = '';
        $this->resetErrorBag();
    }

    public function saveTenant(): void
    {
        $this->checkSuperAdmin();

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('companies', 'code')->ignore($this->selectedCompanyId),
            ],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:30'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'plan_id' => ['required', 'exists:subscription_plans,id'],
        ];

        if (!$this->isEditing) {
            $rules['owner_name'] = ['required', 'string', 'max:191'];
            $rules['owner_email'] = ['required', 'email', 'unique:users,email'];
            $rules['owner_password'] = ['required', 'string', 'min:8'];
            $rules['city'] = ['required', 'string', 'max:100'];
        }

        $validated = $this->validate($rules);

        $plan = SubscriptionPlan::findOrFail($validated['plan_id']);

        if ($this->isEditing) {
            /** @var Company $company */
            $company = Company::findOrFail($this->selectedCompanyId);
            $company->update([
                'name' => $validated['name'],
                'code' => strtoupper($validated['code']),
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'tax_number' => $validated['tax_number'],
                'address' => $validated['address'],
                'plan_id' => $validated['plan_id'],
            ]);

            app(AuditLogService::class)->log('tenant_updated', $company, null, $company->toArray(), 'Super Admin updated company details.');
            session()->flash('success', 'Tenant company details updated successfully.');
        } else {
            DB::transaction(function () use ($validated, $plan) {
                $slug = Str::slug($validated['name']);
                if (Company::where('slug', $slug)->exists()) {
                    $slug .= '-' . rand(10, 99);
                }

                $company = Company::create([
                    'name' => $validated['name'],
                    'code' => strtoupper($validated['code']),
                    'slug' => $slug,
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'tax_number' => $validated['tax_number'],
                    'status' => $this->is_trial ? 'trial' : 'active',
                    'plan_id' => $plan->id,
                    'settings' => [
                        'bilty_prefix' => strtoupper($validated['code']) . '-LR',
                        'trip_prefix' => strtoupper($validated['code']) . '-TR',
                        'currency' => 'PKR',
                    ],
                ]);

                // Create Default Head Office Branch
                $branch = Branch::create([
                    'company_id' => $company->id,
                    'code' => strtoupper($validated['code']) . '-HQ',
                    'name' => $validated['name'] . ' (Head Office)',
                    'city' => $this->city,
                    'is_head_office' => true,
                    'is_active' => true,
                ]);

                // Find or create tenant_admin role
                $adminRole = Role::firstOrCreate(
                    ['company_id' => null, 'slug' => 'tenant_admin'],
                    [
                        'name' => 'Tenant Owner / Administrator',
                        'description' => 'Full control over company fleet, staff, and operations.',
                        'is_system' => true,
                    ]
                );

                // Create Business Owner User
                $owner = User::create([
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'role_id' => $adminRole->id,
                    'name' => $validated['owner_name'],
                    'email' => $validated['owner_email'],
                    'phone' => $this->owner_phone ?: $validated['phone'],
                    'password' => Hash::make($this->owner_password),
                    'status' => 'active',
                    'is_super_admin' => false,
                ]);

                // Authorize owner for HQ branch
                $owner->branches()->sync([$branch->id]);

                // Set owner on company
                $company->owner_id = $owner->id;
                $company->save();

                // Create Subscription through SubscriptionService
                app(SubscriptionService::class)->createSubscription(
                    $company,
                    $plan,
                    $this->billing_cycle,
                    $this->is_trial
                );

                // Initialize document sequences
                $seqService = app(DocumentSequenceService::class);
                $seqService->getNextSequenceNumber('consignment', strtoupper($validated['code']) . '-LR', 5, $company->id);

                app(AuditLogService::class)->log('tenant_created', $company, null, $company->toArray(), 'Super Admin provisioned new transport company tenant.');
            });

            session()->flash('success', 'New transport company tenant provisioned successfully with owner and subscription.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function editTenant(int $companyId): void
    {
        $this->checkSuperAdmin();
        $company = Company::findOrFail($companyId);

        $this->selectedCompanyId = $company->id;
        $this->name = $company->name;
        $this->code = $company->code;
        $this->email = $company->email;
        $this->phone = $company->phone;
        $this->tax_number = $company->tax_number ?? '';
        $this->address = $company->address ?? '';
        $this->plan_id = $company->plan_id;
        $this->isEditing = true;
        $this->showModal = true;
    }

    public function setStatus(int $companyId, string $status): void
    {
        $this->checkSuperAdmin();
        $company = Company::findOrFail($companyId);
        $oldStatus = $company->status;

        $company->status = $status;
        $company->save();

        app(AuditLogService::class)->log(
            'tenant_status_changed',
            $company,
            ['status' => $oldStatus],
            ['status' => $status],
            sprintf('Super Admin changed tenant status from [%s] to [%s].', $oldStatus, $status)
        );

        session()->flash('success', sprintf('Company [%s] status updated to [%s].', $company->name, $status));
    }

    public function viewTenant(int $companyId): void
    {
        $this->checkSuperAdmin();
        $company = Company::with(['plan', 'owner', 'branches', 'subscriptions.plan'])->findOrFail($companyId);

        $this->viewingCompany = $company;

        $plan = $company->plan;
        $this->usageStats = [
            'users' => [
                'current' => $company->users()->count(),
                'limit' => $plan ? $plan->max_users : 'N/A',
                'percentage' => ($plan && $plan->max_users > 0) ? min(100, round(($company->users()->count() / $plan->max_users) * 100)) : 0,
            ],
            'branches' => [
                'current' => $company->branches()->count(),
                'limit' => $plan ? $plan->max_branches : 'N/A',
                'percentage' => ($plan && $plan->max_branches > 0) ? min(100, round(($company->branches()->count() / $plan->max_branches) * 100)) : 0,
            ],
            'vehicles' => [
                'current' => 0,
                'limit' => $plan ? $plan->max_vehicles : 'N/A',
                'percentage' => 0,
            ],
        ];

        $this->showDetailModal = true;
    }

    public function toggleFeatureOverride(int $companyId, string $featureSlug): void
    {
        $this->checkSuperAdmin();
        $company = Company::findOrFail($companyId);

        $currentAccess = $company->hasFeature($featureSlug);
        $newAccess = !$currentAccess;

        app(FeatureManagerService::class)->setFeatureOverride($company, $featureSlug, $newAccess);

        // Refresh viewing company
        $this->viewingCompany = $company->fresh();
        session()->flash('success', sprintf('Feature [%s] override set to [%s].', $featureSlug, $newAccess ? 'Enabled' : 'Disabled'));
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->viewingCompany = null;
    }
}
