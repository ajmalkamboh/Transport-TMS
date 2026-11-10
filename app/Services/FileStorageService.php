<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileStorageService
{
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Store an uploaded file in the tenant's isolated private storage directory.
     *
     * @param UploadedFile $file
     * @param string $category e.g., 'pods', 'driver_licenses', 'invoices'
     * @param int|null $explicitCompanyId
     * @return string Stored relative file path
     * @throws AuthorizationException
     */
    public function storeTenantFile(
        UploadedFile $file,
        string $category = 'general',
        ?int $explicitCompanyId = null
    ): string {
        $companyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        if ($companyId === null) {
            throw new AuthorizationException('Cannot store tenant document without active tenant context.');
        }

        // Validate MIME type
        $mime = $file->getMimeType();
        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException(sprintf('Disallowed file MIME type: [%s].', $mime));
        }

        $year = date('Y');
        $directory = sprintf('private/companies/%d/%s/%s', $companyId, $category, $year);

        $path = $file->store($directory, 'local');

        if (!$path) {
            throw new \RuntimeException('Failed to store private tenant file.');
        }

        return $path;
    }

    /**
     * Download or stream a tenant file with strict tenant isolation verification.
     *
     * @param string $filePath
     * @param string|null $displayName
     * @param int|null $explicitCompanyId
     * @return StreamedResponse
     * @throws AuthorizationException
     */
    public function getTenantFileStream(
        string $filePath,
        ?string $displayName = null,
        ?int $explicitCompanyId = null
    ): StreamedResponse {
        $companyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        if ($companyId === null && !$this->tenantContext->isSuperAdmin()) {
            throw new AuthorizationException('Cannot download tenant document without active tenant context.');
        }

        // Enforce tenant path boundary
        $tenantPrefix = sprintf('private/companies/%d/', $companyId);

        if (!$this->tenantContext->isSuperAdmin() && !str_starts_with($filePath, $tenantPrefix)) {
            throw new AuthorizationException('Cross-tenant file download prohibited.');
        }

        if (!Storage::disk('local')->exists($filePath)) {
            abort(404, 'The requested document does not exist.');
        }

        return Storage::disk('local')->download($filePath, $displayName);
    }

    /**
     * Delete a tenant file with strict isolation check.
     */
    public function deleteTenantFile(string $filePath, ?int $explicitCompanyId = null): bool
    {
        $companyId = $explicitCompanyId ?? $this->tenantContext->getCompanyId();

        $tenantPrefix = sprintf('private/companies/%d/', $companyId);

        if (!$this->tenantContext->isSuperAdmin() && !str_starts_with($filePath, $tenantPrefix)) {
            throw new AuthorizationException('Cross-tenant file deletion prohibited.');
        }

        return Storage::disk('local')->delete($filePath);
    }
}

