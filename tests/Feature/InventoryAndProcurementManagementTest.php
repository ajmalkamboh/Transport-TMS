<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryItemManager;
use App\Livewire\Inventory\ProcurementManager;
use App\Livewire\Inventory\StockLedgerManager;
use App\Livewire\Inventory\WarehouseStockManager;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialYear;
use App\Models\GoodsReceiptNote;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseReturn;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Models\UnitOfMeasurement;
use App\Models\UomConversion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\ChartOfAccountsService;
use App\Services\FinancialPeriodService;
use App\Services\InventoryService;
use App\Services\ProcurementService;
use App\Services\TenantContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryAndProcurementManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected Branch $branch;
    protected User $adminUser;
    protected Warehouse $centralWarehouse;
    protected Warehouse $workshopWarehouse;
    protected UnitOfMeasurement $pieceUom;
    protected UnitOfMeasurement $boxUom;
    protected UnitOfMeasurement $literUom;
    protected InventoryCategory $filtersCategory;
    protected Supplier $partsSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Apex Logistics Ltd',
            'legal_name' => 'Apex Logistics Limited',
            'slug' => 'apex-logistics',
            'code' => 'APX',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->branch = Branch::create([
            'company_id' => $this->company->id,
            'name' => 'Karachi Central Depot',
            'code' => 'KHI-01',
            'city' => 'Karachi',
            'is_active' => true,
        ]);

        $adminRole = \App\Models\Role::create([
            'company_id' => null,
            'name' => 'Tenant Administrator',
            'slug' => 'tenant_admin',
            'description' => 'Administrator for tenant organization',
            'is_system' => true,
        ]);

        $this->adminUser = User::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'role_id' => $adminRole->id,
            'name' => 'Store Manager',
            'email' => 'store@apexlogistics.com',
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'is_super_admin' => false,
        ]);
        $this->company->update(['owner_id' => $this->adminUser->id]);

        // Initialize tenant context and seed chart of accounts
        app(TenantContextService::class)->setTenantContext($this->company, $this->branch);
        app(ChartOfAccountsService::class)->seedDefaultChartOfAccounts($this->company);

        // Open current financial year and accounting periods
        app(FinancialPeriodService::class)->createFinancialYearWithPeriods(
            company: $this->company,
            name: 'FY 2026',
            startDate: now()->startOfYear()->toDateString(),
            endDate: now()->endOfYear()->toDateString(),
            makeCurrent: true
        );

        // Create Warehouses
        $this->centralWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'code' => 'WH-KHI-MAIN',
            'name' => 'Central Parts Warehouse',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->workshopWarehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'code' => 'WH-KHI-WORKSHOP',
            'name' => 'Karachi Workshop Store',
            'is_default' => false,
            'is_active' => true,
        ]);

        // Create UOMs
        $this->pieceUom = UnitOfMeasurement::create([
            'company_id' => $this->company->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'symbol' => 'pc',
            'is_active' => true,
        ]);

        $this->boxUom = UnitOfMeasurement::create([
            'company_id' => $this->company->id,
            'name' => 'Box of 10',
            'code' => 'BOX10',
            'symbol' => 'bx',
            'is_active' => true,
        ]);

        $this->literUom = UnitOfMeasurement::create([
            'company_id' => $this->company->id,
            'name' => 'Liters',
            'code' => 'LTR',
            'symbol' => 'L',
            'is_active' => true,
        ]);

        // Create Inventory Category
        $this->filtersCategory = InventoryCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Filters & Elements',
            'code' => 'FLT',
            'is_active' => true,
        ]);

        // Create Supplier
        $this->partsSupplier = Supplier::create([
            'company_id' => $this->company->id,
            'name' => 'Donaldson Filtration Pakistan',
            'code' => 'SUP-DON-01',
            'contact_name' => 'Tariq Mehmood',
            'phone' => '0300-9876543',
            'email' => 'sales@donaldson.pk',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Test Unit of Measurement Conversion Calculations.
     */
    public function test_uom_conversion_calculations(): void
    {
        $invService = app(InventoryService::class);

        // 1 Box = 10 Pieces
        UomConversion::create([
            'company_id' => $this->company->id,
            'from_uom_id' => $this->boxUom->id,
            'to_uom_id' => $this->pieceUom->id,
            'conversion_factor' => 10.0000,
        ]);

        // Forward conversion: 5 Boxes = 50 Pieces
        $pieces = $invService->convertQuantity($this->company->id, $this->boxUom->id, $this->pieceUom->id, 5.0);
        $this->assertEquals(50.0, $pieces);

        // Reverse conversion: 30 Pieces = 3 Boxes
        $boxes = $invService->convertQuantity($this->company->id, $this->pieceUom->id, $this->boxUom->id, 30.0);
        $this->assertEquals(3.0, $boxes);

        // Same UOM
        $same = $invService->convertQuantity($this->company->id, $this->pieceUom->id, $this->pieceUom->id, 7.5);
        $this->assertEquals(7.5, $same);
    }

    /**
     * 2. Test Weighted Average Cost (WAC) Valuation Engine and Stock Isolation.
     */
    public function test_weighted_average_cost_wac_valuation_engine(): void
    {
        $invService = app(InventoryService::class);

        $oilFilter = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'FLT-OIL-HD',
            'name' => 'Heavy Duty Spin-On Oil Filter',
            'reorder_level' => 10,
            'safety_stock' => 5,
            'average_cost' => 0,
            'last_purchase_cost' => 0,
            'is_active' => true,
        ]);

        // Receipt 1: 10 units @ 1,000 PKR each
        // Total valuation: 10,000 PKR; WAC: 1,000 PKR
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $oilFilter->id,
            quantity: 10,
            unitCost: 1000.0,
            transactionType: StockLedgerEntry::TYPE_GRN,
            refNumber: 'GRN-001',
            user: $this->adminUser
        );

        $stock = WarehouseStock::where('warehouse_id', $this->centralWarehouse->id)
            ->where('inventory_item_id', $oilFilter->id)
            ->first();

        $this->assertNotNull($stock);
        $this->assertEquals(10.0, (float) $stock->quantity_on_hand);
        $this->assertEquals(1000.0, (float) $stock->average_unit_cost);
        $this->assertEquals(10000.0, (float) $stock->total_valuation);

        // Receipt 2: 10 units @ 1,200 PKR each
        // Combined: (10,000 + 12,000) / 20 = 22,000 / 20 = 1,100 PKR
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $oilFilter->id,
            quantity: 10,
            unitCost: 1200.0,
            transactionType: StockLedgerEntry::TYPE_GRN,
            refNumber: 'GRN-002',
            user: $this->adminUser
        );

        $stock->refresh();
        $this->assertEquals(20.0, (float) $stock->quantity_on_hand);
        $this->assertEquals(1100.0, (float) $stock->average_unit_cost);
        $this->assertEquals(22000.0, (float) $stock->total_valuation);

        // Warehouse Isolation: Workshop warehouse stock must still be zero
        $workshopStock = WarehouseStock::where('warehouse_id', $this->workshopWarehouse->id)
            ->where('inventory_item_id', $oilFilter->id)
            ->first();

        $this->assertTrue(!$workshopStock || (float) $workshopStock->quantity_on_hand === 0.0);
    }

    /**
     * 3. Test Negative Stock Prohibition.
     */
    public function test_negative_stock_is_strictly_prohibited(): void
    {
        $invService = app(InventoryService::class);

        $airFilter = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'FLT-AIR-HD',
            'name' => 'Heavy Duty Air Filter',
            'reorder_level' => 5,
            'safety_stock' => 2,
            'is_active' => true,
        ]);

        // Warehouse has 0 units initially. Attempting to issue 1 unit must throw InvalidArgumentException!
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Negative stock is prohibited/i');

        $invService->issueStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $airFilter->id,
            quantity: 1,
            transactionType: StockLedgerEntry::TYPE_ISSUE_GENERAL,
            user: $this->adminUser
        );
    }

    /**
     * 4. Test Inter-Warehouse Stock Transfer.
     */
    public function test_inter_warehouse_stock_transfer(): void
    {
        $invService = app(InventoryService::class);

        $item = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'TYR-PATCH',
            'name' => 'Tubeless Tyre Puncture Patch',
            'is_active' => true,
        ]);

        // Receive 50 units into Central Warehouse @ 200 PKR
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $item->id,
            quantity: 50,
            unitCost: 200.0,
            transactionType: StockLedgerEntry::TYPE_OPENING_STOCK,
            refNumber: 'OPN-01',
            user: $this->adminUser
        );

        // Transfer 20 units to Workshop Warehouse
        $transfer = StockTransfer::create([
            'company_id' => $this->company->id,
            'transfer_number' => 'ST-0001',
            'from_warehouse_id' => $this->centralWarehouse->id,
            'to_warehouse_id' => $this->workshopWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'status' => StockTransfer::STATUS_DRAFT,
            'total_cost' => 0,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'inventory_item_id' => $item->id,
            'quantity_transferred' => 20,
            'quantity_received' => 0,
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);

        $invService->transferStock($transfer, $this->adminUser);

        // Check source warehouse: 50 - 20 = 30
        $srcStock = WarehouseStock::where('warehouse_id', $this->centralWarehouse->id)
            ->where('inventory_item_id', $item->id)
            ->first();
        $this->assertEquals(30.0, (float) $srcStock->quantity_on_hand);

        // Check destination warehouse: 0 + 20 = 20 @ 200 PKR
        $dstStock = WarehouseStock::where('warehouse_id', $this->workshopWarehouse->id)
            ->where('inventory_item_id', $item->id)
            ->first();
        $this->assertEquals(20.0, (float) $dstStock->quantity_on_hand);
        $this->assertEquals(200.0, (float) $dstStock->average_unit_cost);
        $this->assertEquals(4000.0, (float) $dstStock->total_valuation);

        // Transfer status must be completed
        $transfer->refresh();
        $this->assertEquals(StockTransfer::STATUS_COMPLETED, $transfer->status);
    }

    /**
     * 5. Test Vehicle Maintenance Job Card Spare Parts Issuance & Accounting Separation.
     * Crucial Principle: Avoid recording both purchase and consumption as the same expense!
     */
    public function test_job_card_parts_issuance_and_accounting_integration(): void
    {
        $invService = app(InventoryService::class);

        $brakePad = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'BRK-PAD-01',
            'name' => 'Brake Lining Set Heavy Prime Mover',
            'is_active' => true,
        ]);

        // 1. Receive 10 sets @ 5,000 PKR each into Workshop Warehouse
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->workshopWarehouse->id,
            itemId: $brakePad->id,
            quantity: 10,
            unitCost: 5000.0,
            transactionType: StockLedgerEntry::TYPE_GRN,
            refNumber: 'GRN-BRK-01',
            user: $this->adminUser
        );

        // Book Supplier Bill for parts purchased (Dr. 1300 Spares Inventory 50,000 / Cr. 2110 AP Suppliers 50,000)
        app(ProcurementService::class)->createSupplierBill(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'bill_number' => 'BILL-BRK-001',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
            ],
            [
                [
                    'inventory_item_id' => $brakePad->id,
                    'description' => 'Heavy Duty Brake Lining Sets',
                    'quantity' => 10,
                    'unit_rate' => 5000.0,
                ],
            ],
            $this->adminUser
        );

        // Create Fleet Vehicle & Job Card
        $vehicle = Vehicle::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'registration_number' => 'TLR-9090',
            'fleet_number' => 'FL-909',
            'ownership_type' => Vehicle::OWNERSHIP_COMPANY,
            'vehicle_category' => 'HCV',
            'make' => 'Hino',
            'model' => '500 Series',
            'manufacturing_year' => 2022,
            'chassis_number' => 'CH-90901',
            'engine_number' => 'ENG-90901',
            'payload_capacity_tons' => 45.0,
            'fuel_type' => 'DIESEL',
            'current_odometer_km' => 85000,
            'status' => Vehicle::STATUS_MAINTENANCE,
        ]);

        $jobCard = JobCard::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'job_card_number' => 'JC-2026-0001',
            'vehicle_id' => $vehicle->id,
            'workshop_type' => JobCard::WORKSHOP_IN_HOUSE,
            'workshop_name' => 'Central Fleet Depot Workshop',
            'service_type' => JobCard::SERVICE_REPAIR,
            'priority' => 'high',
            'odometer_km' => 85000,
            'scheduled_date' => now()->toDateString(),
            'labour_charges' => 2500.0,
            'parts_total_cost' => 0.0,
            'tax_amount' => 0.0,
            'total_cost' => 2500.0,
            'status' => JobCard::STATUS_IN_PROGRESS,
            'issue_description' => 'Rear axle brake failure inspection',
        ]);

        // Issue 2 sets of brake pads to this Job Card
        $issuedItems = $invService->issuePartsToJobCard($jobCard, [
            [
                'inventory_item_id' => $brakePad->id,
                'warehouse_id' => $this->workshopWarehouse->id,
                'quantity' => 2,
                'notes' => 'Installed on rear axle left & right',
            ],
        ], $this->adminUser);

        $this->assertCount(1, $issuedItems);

        // Workshop Warehouse stock reduced from 10 to 8
        $stock = WarehouseStock::where('warehouse_id', $this->workshopWarehouse->id)
            ->where('inventory_item_id', $brakePad->id)
            ->first();
        $this->assertEquals(8.0, (float) $stock->quantity_on_hand);

        // Job Card totals updated
        $jobCard->refresh();
        $this->assertEquals(10000.0, (float) $jobCard->parts_total_cost); // 2 * 5,000
        $this->assertEquals(12500.0, (float) $jobCard->total_cost); // 2,500 labour + 10,000 parts

        // Verify Double-Entry Accounting Voucher:
        // Dr. 5400-VEHICLE-MAINTENANCE (10,000)
        // Cr. 1300-SPARES-INVENTORY (10,000)
        $maintenanceAccount = ChartOfAccount::where('company_id', $this->company->id)
            ->where('account_code', ChartOfAccount::CODE_VEHICLE_MAINTENANCE)
            ->first();

        $inventoryAccount = ChartOfAccount::where('company_id', $this->company->id)
            ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
            ->first();

        $this->assertEquals(10000.0, (float) $maintenanceAccount->current_balance);
        $this->assertEquals(40000.0, (float) $inventoryAccount->current_balance); // 50,000 received - 10,000 consumed
    }

    /**
     * 6. Test Complete Procurement Workflow (PO -> GRN -> Supplier Bill -> Payment).
     */
    public function test_complete_procurement_lifecycle(): void
    {
        $procService = app(ProcurementService::class);

        $engineOil = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->literUom->id,
            'item_code' => 'OIL-15W40',
            'name' => 'Rimula R4X 15W-40 Heavy Engine Oil',
            'reorder_level' => 100,
            'safety_stock' => 50,
            'is_active' => true,
        ]);

        // Step 1: Create and Approve Purchase Order (100 Liters @ 1,500 PKR)
        $po = $procService->createPurchaseOrder(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'warehouse_id' => $this->centralWarehouse->id,
                'branch_id' => $this->branch->id,
                'order_date' => now()->toDateString(),
            ],
            [
                [
                    'inventory_item_id' => $engineOil->id,
                    'quantity_ordered' => 100,
                    'unit_rate' => 1500.0,
                    'tax_rate' => 0,
                ],
            ],
            $this->adminUser
        );

        $this->assertEquals(150000.0, (float) $po->total_amount);
        $procService->approvePurchaseOrder($po, $this->adminUser);
        $this->assertEquals(PurchaseOrder::STATUS_APPROVED, $po->status);

        // Step 2: Receive Goods via GRN (100 Liters @ 1,500 PKR)
        $grn = $procService->recordGoodsReceipt(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'warehouse_id' => $this->centralWarehouse->id,
                'purchase_order_id' => $po->id,
                'supplier_challan_number' => 'DC-88123',
                'received_date' => now()->toDateString(),
            ],
            [
                [
                    'inventory_item_id' => $engineOil->id,
                    'purchase_order_item_id' => $po->items->first()->id,
                    'quantity_received' => 100,
                    'unit_rate' => 1500.0,
                ],
            ],
            $this->adminUser
        );

        $po->refresh();
        $this->assertEquals(PurchaseOrder::STATUS_RECEIVED, $po->status);

        // Stock in Central Warehouse is now 100 Liters @ 1,500 PKR
        $stock = WarehouseStock::where('warehouse_id', $this->centralWarehouse->id)
            ->where('inventory_item_id', $engineOil->id)
            ->first();
        $this->assertEquals(100.0, (float) $stock->quantity_on_hand);
        $this->assertEquals(1500.0, (float) $stock->average_unit_cost);
        $this->assertEquals(150000.0, (float) $stock->total_valuation);

        // Step 3: Create Supplier Bill / Invoice
        // Posts GL: Dr. 1300 Spares Inventory (Asset) / Cr. 2110 AP Suppliers (Liability)
        $bill = $procService->createSupplierBill(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'goods_receipt_note_id' => $grn->id,
                'bill_number' => 'INV-DON-5544',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
            ],
            [
                [
                    'inventory_item_id' => $engineOil->id,
                    'description' => 'Rimula 15W40 Engine Oil Drums',
                    'quantity' => 100,
                    'unit_rate' => 1500.0,
                    'tax_amount' => 0,
                ],
            ],
            $this->adminUser
        );

        $this->assertEquals(150000.0, (float) $bill->total_amount);
        $this->assertNotNull($bill->journal_voucher_id);

        $this->partsSupplier->refresh();
        $this->assertEquals(150000.0, (float) $this->partsSupplier->current_balance);

        // Verify GL Balances:
        $inventoryAccount = ChartOfAccount::where('company_id', $this->company->id)
            ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
            ->first();
        $payableAccount = ChartOfAccount::where('company_id', $this->company->id)
            ->where('account_code', ChartOfAccount::CODE_AP_TRADE_SUPPLIERS)
            ->first();

        $this->assertEquals(150000.0, (float) $inventoryAccount->current_balance);
        $this->assertEquals(150000.0, (float) $payableAccount->current_balance);

        // Step 4: Record Supplier Payment (Full 150,000 PKR payment)
        // Posts GL: Dr. 2110 AP Suppliers / Cr. 1020 Bank Accounts
        $payment = $procService->recordSupplierPayment(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'supplier_bill_id' => $bill->id,
                'amount' => 150000.0,
                'payment_method' => SupplierPayment::METHOD_BANK_TRANSFER,
                'payment_date' => now()->toDateString(),
                'reference_number' => 'TXN-HBL-998822',
            ],
            $this->adminUser
        );

        $bill->refresh();
        $this->assertEquals(SupplierBill::STATUS_PAID, $bill->status);
        $this->assertEquals(0.0, (float) $bill->balance_due);

        $this->partsSupplier->refresh();
        $this->assertEquals(0.0, (float) $this->partsSupplier->current_balance);

        $payableAccount->refresh();
        $this->assertEquals(0.0, (float) $payableAccount->current_balance);
    }

    /**
     * 7. Test Purchase Return (Debit Note & Stock Reductions).
     */
    public function test_purchase_return_debit_note(): void
    {
        $procService = app(ProcurementService::class);
        $invService = app(InventoryService::class);

        $tyre = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'TYR-295-80',
            'name' => 'General Radial Tyre 295/80R22.5',
            'is_active' => true,
        ]);

        // Receive 4 tyres @ 45,000 PKR = 180,000 PKR
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $tyre->id,
            quantity: 4,
            unitCost: 45000.0,
            transactionType: StockLedgerEntry::TYPE_GRN,
            refNumber: 'GRN-TYR-01',
            user: $this->adminUser
        );

        // Create Supplier Bill for 180,000 PKR
        $bill = $procService->createSupplierBill(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'bill_number' => 'BILL-TYR-99',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
            ],
            [
                [
                    'inventory_item_id' => $tyre->id,
                    'description' => 'General Radial Tyres',
                    'quantity' => 4,
                    'unit_rate' => 45000.0,
                ],
            ],
            $this->adminUser
        );

        $this->partsSupplier->refresh();
        $this->assertEquals(180000.0, (float) $this->partsSupplier->current_balance);

        // Return 1 defective tyre to supplier (Debit Note for 45,000 PKR)
        $return = $procService->recordPurchaseReturn(
            [
                'company_id' => $this->company->id,
                'supplier_id' => $this->partsSupplier->id,
                'warehouse_id' => $this->centralWarehouse->id,
                'return_date' => now()->toDateString(),
                'reason' => 'Bead deformation manufacturing defect',
            ],
            [
                [
                    'inventory_item_id' => $tyre->id,
                    'quantity_returned' => 1,
                    'unit_rate' => 45000.0,
                    'tax_amount' => 0,
                ],
            ],
            $this->adminUser
        );

        // Stock reduced from 4 to 3
        $stock = WarehouseStock::where('warehouse_id', $this->centralWarehouse->id)
            ->where('inventory_item_id', $tyre->id)
            ->first();
        $this->assertEquals(3.0, (float) $stock->quantity_on_hand);

        // Supplier balance reduced by 45,000 PKR
        $this->partsSupplier->refresh();
        $this->assertEquals(135000.0, (float) $this->partsSupplier->current_balance);

        // Return completed with journal voucher posted
        $this->assertEquals(PurchaseReturn::STATUS_COMPLETED, $return->status);
        $this->assertNotNull($return->journal_voucher_id);
    }

    /**
     * 8. Test Stock Adjustment & Wastage Write-Off Accounting.
     */
    public function test_stock_adjustment_and_wastage_write_off(): void
    {
        $invService = app(InventoryService::class);

        $coolant = InventoryItem::create([
            'company_id' => $this->company->id,
            'category_id' => $this->filtersCategory->id,
            'primary_uom_id' => $this->literUom->id,
            'item_code' => 'CLT-50-50',
            'name' => 'Radiator Coolant Premixed 50/50',
            'is_active' => true,
        ]);

        // Receive 20 Liters @ 500 PKR = 10,000 PKR
        $invService->receiveStock(
            companyId: $this->company->id,
            warehouseId: $this->centralWarehouse->id,
            itemId: $coolant->id,
            quantity: 20,
            unitCost: 500.0,
            transactionType: StockLedgerEntry::TYPE_OPENING_STOCK,
            user: $this->adminUser
        );

        // Annual physical count finds only 16 Liters (4 Liters damaged/spilled)
        $adj = StockAdjustment::create([
            'company_id' => $this->company->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'adjustment_number' => 'ADJ-2026-001',
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => StockAdjustment::TYPE_DAMAGE,
            'reason' => 'Canister punctured en-route in warehouse',
            'total_cost' => 0,
            'status' => StockAdjustment::STATUS_DRAFT,
            'created_by' => $this->adminUser->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adj->id,
            'inventory_item_id' => $coolant->id,
            'system_quantity' => 20,
            'counted_quantity' => 16,
            'quantity_difference' => -4,
            'unit_cost' => 500.0,
            'total_difference_cost' => -2000.0,
            'notes' => 'Punctured canister write-off',
        ]);

        $invService->adjustStock($adj, $this->adminUser);

        // Warehouse balance reduced to 16
        $stock = WarehouseStock::where('warehouse_id', $this->centralWarehouse->id)
            ->where('inventory_item_id', $coolant->id)
            ->first();
        $this->assertEquals(16.0, (float) $stock->quantity_on_hand);

        // General Ledger posted: Dr. 5490 Inventory Write-off (2,000) / Cr. 1300 Spares Inventory (2,000)
        $adj->refresh();
        $this->assertEquals(StockAdjustment::STATUS_APPROVED, $adj->status);
        $this->assertNotNull($adj->journal_voucher_id);

        $writeOffAccount = ChartOfAccount::where('company_id', $this->company->id)
            ->where('account_code', ChartOfAccount::CODE_INVENTORY_WRITE_OFF)
            ->first();
        $this->assertEquals(2000.0, (float) $writeOffAccount->current_balance);
    }

    /**
     * 9. Test Livewire 3 Components Rendering & UI Actions.
     */
    public function test_livewire_inventory_and_procurement_components(): void
    {
        $this->actingAs($this->adminUser);

        // 1. InventoryItemManager
        Livewire::test(InventoryItemManager::class)
            ->assertStatus(200)
            ->assertSee('Inventory Items')
            ->set('name', 'Battery Heavy Duty 12V 200Ah')
            ->set('item_code', 'BAT-200AH')
            ->set('primary_uom_id', $this->pieceUom->id)
            ->set('reorder_level', 4)
            ->set('safety_stock', 2)
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('inventory_items', [
            'company_id' => $this->company->id,
            'item_code' => 'BAT-200AH',
            'name' => 'Battery Heavy Duty 12V 200Ah',
        ]);

        // 2. WarehouseStockManager
        Livewire::test(WarehouseStockManager::class)
            ->assertStatus(200)
            ->assertSee('Warehouse Stocks')
            ->assertSee('Central Parts Warehouse');

        // 3. ProcurementManager
        Livewire::test(ProcurementManager::class)
            ->assertStatus(200)
            ->assertSee('Procurement')
            ->assertSee('Purchase Orders');

        // 4. StockLedgerManager
        Livewire::test(StockLedgerManager::class)
            ->assertStatus(200)
            ->assertSee('Stock Ledger');
    }

    /**
     * 10. Test Printable Documents (PO, GRN, Transfer).
     */
    public function test_printable_procurement_and_transfer_documents(): void
    {
        $this->actingAs($this->adminUser);

        $item = InventoryItem::create([
            'company_id' => $this->company->id,
            'primary_uom_id' => $this->pieceUom->id,
            'item_code' => 'TEST-PART',
            'name' => 'Test Printable Part',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'supplier_id' => $this->partsSupplier->id,
            'po_number' => 'PO-TEST-01',
            'order_date' => now()->toDateString(),
            'total_amount' => 10000,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);

        $grn = GoodsReceiptNote::create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->centralWarehouse->id,
            'supplier_id' => $this->partsSupplier->id,
            'grn_number' => 'GRN-TEST-01',
            'received_date' => now()->toDateString(),
            'total_received_amount' => 10000,
            'status' => GoodsReceiptNote::STATUS_VERIFIED,
        ]);

        $transfer = StockTransfer::create([
            'company_id' => $this->company->id,
            'transfer_number' => 'ST-TEST-01',
            'from_warehouse_id' => $this->centralWarehouse->id,
            'to_warehouse_id' => $this->workshopWarehouse->id,
            'transfer_date' => now()->toDateString(),
            'status' => StockTransfer::STATUS_COMPLETED,
        ]);

        $this->get(route('print.purchase-order', $po->id))
            ->assertStatus(200)
            ->assertSee('PO-TEST-01');

        $this->get(route('print.goods-receipt', $grn->id))
            ->assertStatus(200)
            ->assertSee('GRN-TEST-01');

        $this->get(route('print.stock-transfer', $transfer->id))
            ->assertStatus(200)
            ->assertSee('ST-TEST-01');
    }
}
