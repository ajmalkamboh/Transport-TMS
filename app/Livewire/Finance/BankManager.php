<?php

namespace App\Livewire\Finance;

use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\JournalVoucherItem;
use App\Services\BankManagementService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BankManager extends Component
{
    use WithPagination;

    public string $activeTab = 'accounts'; // 'accounts', 'reconciliations'

    // Create Account Modal
    public bool $showCreateAccountModal = false;
    public string $account_name = '';
    public string $account_type = BankAccount::TYPE_BANK;
    public string $bank_name = '';
    public string $branch_name = '';
    public string $account_number = '';
    public string $iban = '';
    public float $opening_balance = 0.00;
    public string $notes = '';

    // Inter-Account Transfer Modal (Contra Voucher)
    public bool $showTransferModal = false;
    public ?int $fromAccountId = null;
    public ?int $toAccountId = null;
    public float $transferAmount = 0.00;
    public string $transferDate = '';
    public string $transferReference = '';

    // Bank Reconciliation Modal
    public bool $showReconcileModal = false;
    public ?int $reconBankAccountId = null;
    public string $statementDate = '';
    public float $statementStartingBalance = 0.00;
    public float $statementEndingBalance = 0.00;
    public array $clearedItemIds = [];
    public float $unclearedDeposits = 0.00;
    public float $unclearedCheques = 0.00;

    public function mount(): void
    {
        $this->transferDate = now()->toDateString();
        $this->statementDate = now()->toDateString();
    }

    public function openCreateAccountModal(): void
    {
        $this->reset(['account_name', 'bank_name', 'branch_name', 'account_number', 'iban', 'opening_balance', 'notes']);
        $this->account_type = BankAccount::TYPE_BANK;
        $this->showCreateAccountModal = true;
    }

    public function saveAccount(): void
    {
        $this->validate([
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|in:bank,cash_in_hand,petty_cash',
            'opening_balance' => 'numeric',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        try {
            app(BankManagementService::class)->createBankAccount([
                'company_id' => $companyId,
                'account_name' => $this->account_name,
                'account_type' => $this->account_type,
                'bank_name' => $this->bank_name,
                'branch_name' => $this->branch_name,
                'account_number' => $this->account_number,
                'iban' => $this->iban,
                'opening_balance' => $this->opening_balance,
                'notes' => $this->notes,
            ], Auth::user());

            session()->flash('success', "Account [{$this->account_name}] created successfully.");
            $this->showCreateAccountModal = false;
        } catch (\Throwable $e) {
            $this->addError('account_name', $e->getMessage());
        }
    }

    public function openTransferModal(): void
    {
        $this->reset(['fromAccountId', 'toAccountId', 'transferAmount', 'transferReference']);
        $this->transferDate = now()->toDateString();
        $this->showTransferModal = true;
    }

    public function confirmTransfer(): void
    {
        $this->validate([
            'fromAccountId' => 'required|exists:bank_accounts,id',
            'toAccountId' => 'required|exists:bank_accounts,id|different:fromAccountId',
            'transferAmount' => 'required|numeric|min:0.01',
            'transferDate' => 'required|date',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $from = BankAccount::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($this->fromAccountId);
        $to = BankAccount::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($this->toAccountId);

        try {
            $voucher = app(BankManagementService::class)->recordTransfer(
                fromAccount: $from,
                toAccount: $to,
                amount: $this->transferAmount,
                transferDate: $this->transferDate,
                reference: $this->transferReference,
                user: Auth::user()
            );

            session()->flash('success', "Transfer of PKR " . number_format($this->transferAmount, 2) . " executed successfully via Contra Voucher [{$voucher->voucher_number}].");
            $this->showTransferModal = false;
        } catch (\Throwable $e) {
            $this->addError('transferAmount', $e->getMessage());
        }
    }

    public function openReconcileModal(int $accountId): void
    {
        $this->reconBankAccountId = $accountId;
        $account = BankAccount::withoutGlobalScopes()->findOrFail($accountId);
        $this->statementStartingBalance = (float) $account->opening_balance;
        $this->statementEndingBalance = (float) $account->current_balance;
        $this->clearedItemIds = [];
        $this->unclearedDeposits = 0.00;
        $this->unclearedCheques = 0.00;
        $this->statementDate = now()->toDateString();
        $this->showReconcileModal = true;
    }

    public function confirmReconciliation(): void
    {
        $this->validate([
            'reconBankAccountId' => 'required|exists:bank_accounts,id',
            'statementDate' => 'required|date',
            'statementEndingBalance' => 'required|numeric',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $account = BankAccount::withoutGlobalScopes()->where('company_id', $companyId)->findOrFail($this->reconBankAccountId);

        try {
            $recon = app(BankManagementService::class)->reconcileBankStatement(
                bankAccount: $account,
                data: [
                    'statement_date' => $this->statementDate,
                    'statement_starting_balance' => $this->statementStartingBalance,
                    'statement_ending_balance' => $this->statementEndingBalance,
                    'cleared_item_ids' => $this->clearedItemIds,
                    'uncleared_deposits' => $this->unclearedDeposits,
                    'uncleared_cheques' => $this->unclearedCheques,
                ],
                user: Auth::user()
            );

            session()->flash('success', "Bank reconciliation completed with status: " . ucfirst($recon->status));
            $this->showReconcileModal = false;
        } catch (\Throwable $e) {
            $this->addError('statementEndingBalance', $e->getMessage());
        }
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $accounts = BankAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with('chartOfAccount')
            ->orderBy('account_type')
            ->get();

        $reconciliations = BankReconciliation::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['bankAccount', 'reconciledByUser'])
            ->latest('statement_date')
            ->paginate(15);

        // Uncleared items for reconciliation modal
        $unreconciledItems = [];
        if ($this->reconBankAccountId) {
            $reconAccount = BankAccount::withoutGlobalScopes()->find($this->reconBankAccountId);
            if ($reconAccount && $reconAccount->chart_of_account_id) {
                $unreconciledItems = JournalVoucherItem::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->where('chart_of_account_id', $reconAccount->chart_of_account_id)
                    ->where('reconciled', false)
                    ->with('voucher')
                    ->latest('id')
                    ->take(50)
                    ->get();
            }
        }

        return view('livewire.finance.bank-manager', compact(
            'accounts',
            'reconciliations',
            'unreconciledItems'
        ));
    }
}
