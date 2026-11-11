<?php

namespace App\Livewire\Finance;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Services\ChartOfAccountsService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ChartOfAccountsManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public bool $showCreateModal = false;

    // Form properties
    public ?int $editingAccountId = null;
    public string $account_code = '';
    public string $account_name = '';
    public string $account_type = ChartOfAccount::TYPE_ASSET;
    public string $account_group = 'current_asset';
    public string $normal_balance = ChartOfAccount::BALANCE_DEBIT;
    public float $opening_balance = 0.00;
    public string $description = '';
    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'account_code' => 'required|string|max:20',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense',
            'account_group' => 'required|string|max:50',
            'normal_balance' => 'required|in:debit,credit',
            'opening_balance' => 'numeric',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }

    public function updatedAccountType($val): void
    {
        $this->normal_balance = in_array($val, ['asset', 'expense'])
            ? ChartOfAccount::BALANCE_DEBIT
            : ChartOfAccount::BALANCE_CREDIT;

        $this->account_group = match ($val) {
            'asset' => 'current_asset',
            'liability' => 'current_liability',
            'equity' => 'equity',
            'revenue' => 'operating_revenue',
            'expense' => 'direct_operating_cost',
            default => 'general',
        };
    }

    public function seedDefaults(): void
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $company = Company::findOrFail($companyId);

        app(ChartOfAccountsService::class)->seedDefaultChartOfAccounts($company);
        session()->flash('success', 'Default Chart of Accounts seeded successfully with transport industry codes.');
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingAccountId', 'account_code', 'account_name', 'description', 'opening_balance']);
        $this->account_type = ChartOfAccount::TYPE_ASSET;
        $this->account_group = 'current_asset';
        $this->normal_balance = ChartOfAccount::BALANCE_DEBIT;
        $this->is_active = true;
        $this->showCreateModal = true;
    }

    public function saveAccount(): void
    {
        $this->validate();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        if ($this->editingAccountId) {
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->findOrFail($this->editingAccountId);

            $account->update([
                'account_name' => $this->account_name,
                'account_group' => $this->account_group,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ]);

            session()->flash('success', "Account [{$account->account_code}] updated successfully.");
        } else {
            // Check uniqueness
            $exists = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('account_code', $this->account_code)
                ->exists();

            if ($exists) {
                $this->addError('account_code', 'This account code is already in use.');
                return;
            }

            ChartOfAccount::create([
                'company_id' => $companyId,
                'account_code' => $this->account_code,
                'account_name' => $this->account_name,
                'account_type' => $this->account_type,
                'account_group' => $this->account_group,
                'normal_balance' => $this->normal_balance,
                'opening_balance' => $this->opening_balance,
                'current_balance' => $this->opening_balance,
                'is_system' => false,
                'is_active' => $this->is_active,
                'description' => $this->description,
            ]);

            session()->flash('success', "Account [{$this->account_code}] created successfully.");
        }

        $this->showCreateModal = false;
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $query = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId);

        if ($this->typeFilter) {
            $query->where('account_type', $this->typeFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('account_code', 'like', "%{$this->search}%")
                  ->orWhere('account_name', 'like', "%{$this->search}%")
                  ->orWhere('account_group', 'like', "%{$this->search}%");
            });
        }

        $accounts = $query->orderBy('account_code')->paginate(20);

        $counts = [
            'total' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->count(),
            'assets' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('account_type', 'asset')->count(),
            'liabilities' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('account_type', 'liability')->count(),
            'equity' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('account_type', 'equity')->count(),
            'revenue' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('account_type', 'revenue')->count(),
            'expenses' => ChartOfAccount::withoutGlobalScopes()->where('company_id', $companyId)->where('account_type', 'expense')->count(),
        ];

        return view('livewire.finance.chart-of-accounts-manager', compact('accounts', 'counts'));
    }
}

