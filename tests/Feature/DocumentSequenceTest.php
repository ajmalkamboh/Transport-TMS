<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Services\DocumentSequenceService;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create([
            'name' => 'Falcon Logistics',
            'code' => 'FL',
            'slug' => 'falcon-logistics',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Indus Logistics',
            'code' => 'IL',
            'slug' => 'indus-logistics',
            'status' => 'active',
        ]);
    }

    /**
     * Test atomic sequence number generation increments consecutively.
     */
    public function test_atomic_sequence_generation(): void
    {
        app(TenantContextService::class)->setCompany($this->companyA);
        $sequenceService = app(DocumentSequenceService::class);
        $year = date('Y');

        $num1 = $sequenceService->getNextSequenceNumber('consignment', 'LR', 5);
        $num2 = $sequenceService->getNextSequenceNumber('consignment', 'LR', 5);
        $num3 = $sequenceService->getNextSequenceNumber('consignment', 'LR', 5);

        $this->assertEquals("LR-{$year}-00001", $num1);
        $this->assertEquals("LR-{$year}-00002", $num2);
        $this->assertEquals("LR-{$year}-00003", $num3);

        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $this->companyA->id,
            'document_type' => 'consignment',
            'year' => $year,
            'last_number' => 3,
        ]);
    }

    /**
     * Test tenant sequences are completely isolated from each other.
     */
    public function test_tenant_sequences_are_isolated(): void
    {
        $sequenceService = app(DocumentSequenceService::class);
        $year = date('Y');

        // Company A generates Bilty 1 & 2
        app(TenantContextService::class)->setCompany($this->companyA);
        $numA1 = $sequenceService->getNextSequenceNumber('consignment', 'FEC-LR', 4);
        $numA2 = $sequenceService->getNextSequenceNumber('consignment', 'FEC-LR', 4);

        $this->assertEquals("FEC-LR-{$year}-0001", $numA1);
        $this->assertEquals("FEC-LR-{$year}-0002", $numA2);

        // Company B generates Bilty 1
        app(TenantContextService::class)->setCompany($this->companyB);
        $numB1 = $sequenceService->getNextSequenceNumber('consignment', 'IFL-LR', 4);

        $this->assertEquals("IFL-LR-{$year}-0001", $numB1);

        // Verify database holds independent counters
        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $this->companyA->id,
            'document_type' => 'consignment',
            'last_number' => 2,
        ]);

        $this->assertDatabaseHas('document_sequences', [
            'company_id' => $this->companyB->id,
            'document_type' => 'consignment',
            'last_number' => 1,
        ]);
    }
}

