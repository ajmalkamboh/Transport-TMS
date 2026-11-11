<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Convenient alias for generating sequence numbers with company and branch parameters.
     */
    public function getNextDocumentNumber(
        int $companyId,
        string $documentType,
        ?int $branchId = null,
        ?string $prefix = null
    ): string {
        return $this->getNextSequenceNumber(
            documentType: $documentType,
            prefix: $prefix,
            explicitCompanyId: $companyId,
            explicitBranchId: $branchId
        );
    }

    /**
     * Generate the next atomic sequence number for any of the 11 document types.
     * Guaranteed safe against concurrent race conditions via pessimistic row locking (SELECT ... FOR UPDATE).
     *
     * @param string $documentType e.g., 'consignment', 'trip', 'invoice', 'quotation', 'booking'
     * @param string|null $prefix Override prefix (e.g. 'FEC-LR')
     * @param int $padding Number of digits (default 5, e.g. '00001')
     * @param int|null $explicitCompanyId Tenant company ID override
     * @param int|null $explicitBranchId Branch ID for branch-specific numbering
     * @return string Formatted unique document number
     */
    public function getNextSequenceNumber(
        string $documentType,
        ?string $prefix = null,
        int $padding = 5,
        ?int $explicitCompanyId = null,
        ?int $explicitBranchId = null
    ): string {
        $companyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        if ($companyId === null) {
            throw new \RuntimeException('Cannot generate document sequence number without tenant company context.');
        }

        $branchId = $explicitBranchId;
        $year = (int) date('Y');

        // Resolve default prefix from DocumentSequence::ALL_TYPES if available
        $defaultConfig = DocumentSequence::ALL_TYPES[$documentType] ?? null;
        $resolvedPrefix = $prefix ?? ($defaultConfig ? $defaultConfig['default_prefix'] : strtoupper(substr($documentType, 0, 3)));

        return DB::transaction(function () use ($companyId, $branchId, $documentType, $year, $resolvedPrefix, $padding) {
            $query = DocumentSequence::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('year', $year);

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            } else {
                $query->whereNull('branch_id');
            }

            /** @var DocumentSequence|null $sequence */
            $sequence = $query->lockForUpdate()->first();

            if (!$sequence) {
                $sequence = DocumentSequence::create([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'document_type' => $documentType,
                    'year' => $year,
                    'prefix' => $resolvedPrefix,
                    'padding' => $padding,
                    'last_number' => 1,
                    'reset_frequency' => 'yearly',
                ]);

                $nextNumber = 1;
            } else {
                $sequence->last_number += 1;
                $sequence->save();
                $nextNumber = $sequence->last_number;
            }

            $padLength = $sequence->padding ?: $padding;
            $formattedNum = str_pad((string) $nextNumber, $padLength, '0', STR_PAD_LEFT);

            // If sequence is branch-specific, append branch code if present
            if ($branchId !== null) {
                $branch = Branch::withoutGlobalScopes()->find($branchId);
                if ($branch) {
                    return sprintf('%s-%s-%s-%s', $sequence->prefix, $branch->code, $year, $formattedNum);
                }
            }

            return sprintf('%s-%s-%s', $sequence->prefix, $year, $formattedNum);
        });
    }

    /**
     * Configure or initialize a custom sequence prefix and padding for a tenant/branch.
     */
    public function configureSequence(
        int $companyId,
        string $documentType,
        string $prefix,
        int $padding = 5,
        ?int $branchId = null,
        ?int $initialNumber = 0
    ): DocumentSequence {
        $year = (int) date('Y');

        return DocumentSequence::withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'document_type' => $documentType,
                'year' => $year,
            ],
            [
                'prefix' => strtoupper($prefix),
                'padding' => $padding,
                'last_number' => $initialNumber,
            ]
        );
    }
}
