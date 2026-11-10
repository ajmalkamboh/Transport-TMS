<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Company A Transport',
            'code' => 'CAT',
            'slug' => 'company-a',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Company B Freight',
            'code' => 'CBF',
            'slug' => 'company-b',
            'status' => 'active',
        ]);

        $this->userA = User::create([
            'company_id' => $this->companyA->id,
            'name' => 'User A',
            'email' => 'user.a@companya.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->userB = User::create([
            'company_id' => $this->companyB->id,
            'name' => 'User B',
            'email' => 'user.b@companyb.local',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
    }

    /**
     * Test tenant can store file in their own private directory.
     */
    public function test_tenant_can_store_private_file(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $fileService = app(FileStorageService::class);
        $file = UploadedFile::fake()->create('signed_pod.pdf', 100, 'application/pdf');

        $path = $fileService->storeTenantFile($file, 'pods');

        $expectedPrefix = sprintf('private/companies/%d/pods/', $this->companyA->id);
        $this->assertStringStartsWith($expectedPrefix, $path);
        Storage::disk('local')->assertExists($path);
    }

    /**
     * Test Tenant A cannot download Tenant B's files.
     */
    public function test_tenant_a_cannot_download_tenant_b_files(): void
    {
        // Company B stores a sensitive document
        $this->actingAs($this->userB);
        app(TenantContextService::class)->setCompany($this->companyB);

        $fileService = app(FileStorageService::class);
        $file = UploadedFile::fake()->create('company_b_financials.pdf', 50, 'application/pdf');
        $pathB = $fileService->storeTenantFile($file, 'contracts');

        // Switch context to Tenant A
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        // Attempting to download Tenant B's file as Tenant A must throw AuthorizationException
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Cross-tenant file download prohibited.');

        $fileService->getTenantFileStream($pathB);
    }

    /**
     * Test executable files are strictly rejected.
     */
    public function test_executable_files_are_rejected(): void
    {
        $this->actingAs($this->userA);
        app(TenantContextService::class)->setCompany($this->companyA);

        $fileService = app(FileStorageService::class);
        $maliciousFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Disallowed file MIME type');

        $fileService->storeTenantFile($maliciousFile, 'pods');
    }
}

