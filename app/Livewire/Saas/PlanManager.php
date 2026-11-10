<?php

namespace App\Livewire\Saas;

use App\Models\SubscriptionPlan;
use App\Services\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlanManager extends Component
{
    public bool $showModal = false;
    public bool $isEditing = false;
    public ?int $selectedPlanId = null;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public float $monthly_price = 0.00;
    public float $quarterly_price = 0.00;
    public float $annual_price = 0.00;
    public int $trial_duration_days = 14;
    public int $max_vehicles = 10;
    public int $max_users = 5;
    public int $max_branches = 2;
    public int $max_storage_mb = 5120;
    public array $features = [];
    public bool $is_active = true;

    protected function checkSuperAdmin(): void
    {
        if (!auth()->check() || !auth()->user()->isSuperAdmin()) {
            throw new AuthorizationException('Access denied: This module requires Platform Super Administrator authority.');
        }
    }

    public function render()
    {
        $this->checkSuperAdmin();

        $plans = SubscriptionPlan::withCount(['companies', 'subscriptions'])
            ->latest()
            ->get();

        return view('livewire.saas.plan-manager', [
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
        $this->selectedPlanId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->monthly_price = 0.00;
        $this->quarterly_price = 0.00;
        $this->annual_price = 0.00;
        $this->trial_duration_days = 14;
        $this->max_vehicles = 10;
        $this->max_users = 5;
        $this->max_branches = 2;
        $this->max_storage_mb = 5120;
        $this->features = [
            'fleet_management',
            'driver_management',
            'customer_management',
            'booking_management',
            'trip_management',
        ];
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function editPlan(int $planId): void
    {
        $this->checkSuperAdmin();
        $plan = SubscriptionPlan::findOrFail($planId);

        $this->selectedPlanId = $plan->id;
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description ?? '';
        $this->monthly_price = (float) $plan->monthly_price;
        $this->quarterly_price = (float) $plan->quarterly_price;
        $this->annual_price = (float) $plan->annual_price;
        $this->trial_duration_days = $plan->trial_duration_days;
        $this->max_vehicles = $plan->max_vehicles;
        $this->max_users = $plan->max_users;
        $this->max_branches = $plan->max_branches;
        $this->max_storage_mb = $plan->max_storage_mb;
        $this->features = $plan->features ?? [];
        $this->is_active = $plan->is_active;

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function savePlan(): void
    {
        $this->checkSuperAdmin();

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'required', 'string', 'max:100',
                Rule::unique('subscription_plans', 'slug')->ignore($this->selectedPlanId),
            ],
            'description' => ['nullable', 'string'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'quarterly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'trial_duration_days' => ['required', 'integer', 'min:0'],
            'max_vehicles' => ['required', 'integer'],
            'max_users' => ['required', 'integer'],
            'max_branches' => ['required', 'integer'],
            'max_storage_mb' => ['required', 'integer'],
            'features' => ['array'],
            'is_active' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        if ($this->isEditing) {
            $plan = SubscriptionPlan::findOrFail($this->selectedPlanId);
            $plan->update($validated);
            app(AuditLogService::class)->log('plan_updated', $plan, null, $validated, 'Super Admin updated subscription plan.');
            session()->flash('success', sprintf('Plan [%s] updated successfully.', $plan->name));
        } else {
            $plan = SubscriptionPlan::create($validated);
            app(AuditLogService::class)->log('plan_created', $plan, null, $validated, 'Super Admin created new subscription plan.');
            session()->flash('success', sprintf('New subscription plan [%s] created successfully.', $plan->name));
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function togglePlanStatus(int $planId): void
    {
        $this->checkSuperAdmin();
        $plan = SubscriptionPlan::findOrFail($planId);
        $plan->is_active = !$plan->is_active;
        $plan->save();

        session()->flash('success', sprintf('Plan [%s] is now %s.', $plan->name, $plan->is_active ? 'Active' : 'Deactivated'));
    }
}

