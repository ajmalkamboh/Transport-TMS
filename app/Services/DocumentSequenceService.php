<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Generate the next atomic sequence number for a document type.
     * Guaranteed safe against concurrent race conditions via pessimistic row locking.
     *
     * @param string $documentType e.g., 'consignment', 'trip', 'invoice', 'voucher'
     * @param string|null $prefix e.g., 'LR', 'TRIP', 'INV'
     * @param int $padding e.g., 5 produces '00001'
     * @param int|null $explicitCompanyId
     * @return string
     */
    public function getNextSequenceNumber(
        string $documentType,
        ?string $prefix = null,
        int $padding = 5,
        ?int $explicitCompanyId = null
    ): string {
        $companyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        if ($companyId === null) {
            throw new \RuntimeException('Cannot generate sequence number without tenant company context.');
        }

        $year = date('Y');
        $resolvedPrefix = $prefix ?? strtoupper(substr($documentType, 0, 3));

        return DB::transaction(function () use ($companyId, $documentType, $year, $resolvedPrefix, $padding) {
            /** @var DocumentSequence $sequence */
            $sequence = DocumentSequence::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = DocumentSequence::create([
                    'company_id' => $companyId,
                    'document_type' => $documentType,
                    'year' => $year,
                    'prefix' => $resolvedPrefix,
                    'last_number' => 1,
                ]);

                $nextNumber = 1;
            } else {
                $sequence->last_number += 1;
                $sequence->save();
                $nextNumber = $sequence->last_number;
            }

            return sprintf('%s-%s-%s', $sequence->prefix, $year, str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT));
        });
    }
}

