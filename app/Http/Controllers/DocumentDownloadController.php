<?php

namespace App\Http\Controllers;

use App\Services\FileStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __construct(
        protected FileStorageService $fileStorageService
    ) {}

    /**
     * Download or preview a tenant document securely.
     */
    public function download(Request $request): StreamedResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'name' => ['nullable', 'string'],
        ]);

        return $this->fileStorageService->getTenantFileStream(
            $request->query('path'),
            $request->query('name')
        );
    }
}

