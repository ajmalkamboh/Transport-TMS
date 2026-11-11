<?php

namespace App\Livewire\Finance;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalVoucher;
use App\Services\GeneralLedgerService;
use App\Services\TenantContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class GeneralLedgerManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $typeFilter = '';
    public string $statusFilter = '';

    // Create Voucher Modal State
    public bool $showCreateModal = false;
    public string $voucher_date = '';
    public string $voucher_type = JournalVoucher::TYPE_JOURNAL;
    public ?int $branch_id = null;
    public string $reference_number = '';
    public string $narration = '';
    public array $lines = [];

    // Reversal Modal State
    public bool $showReverseModal = false;
    public ?int $selectedVoucherId = null;
    public string $reversalReason = '';

    public function mount(): void
    {
        $this->voucher_date = now()->toDateString();
        $this->resetLines();
    }

    public function resetLines(): void
    {
        $this->lines = [
            ['chart_of_account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
            ['chart_of_account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''],
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['chart_of_account_id' => '', 'description' => '', 'debit' => '', 'credit' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 2) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function openCreateModal(): void
    {
        $this->voucher_date = now()->toDateString();
        $this->voucher_type = JournalVoucher::TYPE_JOURNAL;
        $this->reference_number = '';
        $this->narration = '';
        $this->resetLines();
        $this->showCreateModal = true;
    }

    public function saveVoucher(): void
    {
        $this->validate([
            'voucher_date' => 'required|date',
            'voucher_type' => 'required|string',
            'narration' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.chart_of_account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        // Process items
        $items = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($this->lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $items[] = [
                'chart_of_account_id' => $line['chart_of_account_id'],
                'description' => $line['description'] ?: $this->narration,
                'debit' => $debit,
                'credit' => $credit,
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (count($items) < 2) {
            $this->addError('lines', 'A voucher must have at least two valid non-zero lines.');
            return;
        }

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            $this->addError('lines', sprintf('Unbalanced voucher! Total Debits [%0.2f] must equal Total Credits [%0.2f].', $totalDebit, $totalCredit));
            return;
        }

        try {
            app(GeneralLedgerService::class)->postVoucher([
                'company_id' => $companyId,
                'branch_id' => $this->branch_id,
                'voucher_date' => $this->voucher_date,
                'voucher_type' => $this->voucher_type,
                'reference_number' => $this->reference_number,
                'narration' => $this->narration,
                'items' => $items,
            ], Auth::user());

            session()->flash('success', 'Journal voucher posted successfully.');
            $this->showCreateModal = false;
        } catch (\Throwable $e) {
            $this->addError('lines', $e->getMessage());
        }
    }

    public function openReverseModal(int $voucherId): void
    {
        $this->selectedVoucherId = $voucherId;
        $this->reversalReason = '';
        $this->showReverseModal = true;
    }

    public function confirmReverse(): void
    {
        $this->validate([
            'reversalReason' => 'required|string|min:5|max:255',
        ]);

        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;
        $voucher = JournalVoucher::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->findOrFail($this->selectedVoucherId);

        try {
            app(GeneralLedgerService::class)->reverseVoucher($voucher, $this->reversalReason, Auth::user());
            session()->flash('success', "Voucher [{$voucher->voucher_number}] reversed successfully with offsetting journal entries.");
            $this->showReverseModal = false;
        } catch (\Throwable $e) {
            $this->addError('reversalReason', $e->getMessage());
        }
    }

    public function render()
    {
        $companyId = app(TenantContextService::class)->getCompanyId() ?? Auth::user()->company_id;

        $query = JournalVoucher::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->with(['branch', 'createdByUser', 'postedByUser', 'items.account']);

        if ($this->typeFilter) {
            $query->where('voucher_type', $this->typeFilter);
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('voucher_number', 'like', "%{$this->search}%")
                  ->orWhere('reference_number', 'like', "%{$this->search}%")
                  ->orWhere('narration', 'like', "%{$this->search}%");
            });
        }

        $vouchers = $query->orderByDesc('voucher_date')->orderByDesc('id')->paginate(15);

        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $branches = Branch::withoutGlobalScopes()->where('company_id', $companyId)->get();

        return view('livewire.finance.general-ledger-manager', compact('vouchers', 'accounts', 'branches'));
    }
}
