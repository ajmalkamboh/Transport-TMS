<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FinancialYear;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class FinancialPeriodService
{
    /**
     * Validate that a financial transaction date falls within an open financial year
     * and an open accounting period for the company.
     *
     * @throws AuthorizationException
     */
    public function validateTransactionDate(Company|int $company, string|Carbon $date): bool
    {
        $companyId = $company instanceof Company ? $company->id : $company;
        $carbonDate = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();
        $dateStr = $carbonDate->toDateString();

        // 1. Locate the financial year covering this date
        /** @var FinancialYear|null $fy */
        $fy = FinancialYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->first();

        if (!$fy) {
            throw new AuthorizationException(
                sprintf('Transaction rejected: Date [%s] does not fall within any configured Financial Year for this company.', $dateStr)
            );
        }

        if ($fy->isClosed()) {
            throw new AuthorizationException(
                sprintf('Transaction rejected: Financial Year [%s] is CLOSED. No postings or modifications permitted.', $fy->name)
            );
        }

        // 2. Locate the specific accounting period covering this date
        /** @var AccountingPeriod|null $period */
        $period = AccountingPeriod::withoutGlobalScopes()
            ->where('financial_year_id', $fy->id)
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->first();

        if ($period && $period->isClosed()) {
            throw new AuthorizationException(
                sprintf('Transaction rejected: Accounting Period [%s] is CLOSED. Postings into closed periods are restricted.', $period->name)
            );
        }

        return true;
    }

    /**
     * Create a new Financial Year and automatically generate its 12 accounting periods.
     */
    public function createFinancialYearWithPeriods(
        Company $company,
        string $name,
        string $startDate,
        string $endDate,
        bool $makeCurrent = true
    ): FinancialYear {
        return DB::transaction(function () use ($company, $name, $startDate, $endDate, $makeCurrent) {
            if ($makeCurrent) {
                FinancialYear::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->update(['is_current' => false]);
            }

            /** @var FinancialYear $fy */
            $fy = FinancialYear::create([
                'company_id' => $company->id,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => 'open',
                'is_current' => $makeCurrent,
                'opening_balances_locked' => false,
            ]);

            // Generate monthly accounting periods from start_date to end_date
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $currentMonth = $start->copy();
            $periodNum = 1;

            while ($currentMonth->lessThan($end) && $periodNum <= 12) {
                $periodStart = $currentMonth->copy()->startOfMonth();
                $periodEnd = $currentMonth->copy()->endOfMonth();

                // Clamp to financial year boundaries
                if ($periodStart->lessThan($start)) {
                    $periodStart = $start->copy();
                }
                if ($periodEnd->greaterThan($end)) {
                    $periodEnd = $end->copy();
                }

                AccountingPeriod::create([
                    'company_id' => $company->id,
                    'financial_year_id' => $fy->id,
                    'name' => $currentMonth->format('M Y'),
                    'period_number' => $periodNum,
                    'start_date' => $periodStart->toDateString(),
                    'end_date' => $periodEnd->toDateString(),
                    'status' => 'open',
                ]);

                $currentMonth->addMonthNoOverflow();
                $periodNum++;
            }

            return $fy;
        });
    }

    /**
     * Close an accounting period.
     */
    public function closeAccountingPeriod(AccountingPeriod $period, int $userId): void
    {
        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => $userId,
        ]);
    }

    /**
     * Reopen a closed accounting period.
     */
    public function reopenAccountingPeriod(AccountingPeriod $period): void
    {
        $period->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * Close an entire financial year and lock all its periods.
     */
    public function closeFinancialYear(FinancialYear $fy, ?string $notes = null): void
    {
        DB::transaction(function () use ($fy, $notes) {
            $fy->update([
                'status' => 'closed',
                'closing_notes' => $notes,
            ]);

            $fy->periods()->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);
        });
    }

    /**
     * Get active current financial year for a company.
     */
    public function getCurrentFinancialYear(Company|int $company): ?FinancialYear
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        return FinancialYear::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_current', true)
            ->first();
    }
}
