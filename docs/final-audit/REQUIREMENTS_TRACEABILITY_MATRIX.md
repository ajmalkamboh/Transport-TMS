# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — REQUIREMENTS TRACEABILITY MATRIX (RTM)

**Document Reference:** TMS-RTM-001  
**Audit Date:** November 11, 2026  
**Auditor:** Lead Systems Architect & QA Lead  
**Coverage:** All 13 Development Phases (Phase 0 through Phase 12)  
**Status Key:**
- **PASS**: Fully implemented, verified with automated tests and architectural code review.
- **PARTIAL**: Implemented but requires minor extension or external configuration.
- **FAIL**: Defective or broken.
- **MISSING**: Not implemented.
- **NOT VERIFIED**: Lacking verification evidence.
- **NOT APPLICABLE**: Explicitly excluded from approved scope.

---

## 1. Traceability Matrix

| Requirement ID | Phase | Module | Original Requirement | Implementation Location | Verification Method | Result | Evidence | Remarks |
| :--- | :---: | :--- | :--- | :--- | :--- | :---: | :--- | :--- |
| **REQ-00-01** | Phase 0 | Architecture | Multi-tenant Laravel project structure with PSR-4 | `app/`, `bootstrap/app.php`, `config/` | Codebase & composer audit | **PASS** | `composer.json`, `app/` structure | Standards compliant |
| **REQ-00-02** | Phase 0 | Database | Relational database schema with strict constraints | `database/migrations/` | Schema inspection | **PASS** | 40 migration files | Foreign keys & indexes verified |
| **REQ-00-03** | Phase 0 | Precision | High-precision decimals for currency and rates | Migrations & Eloquent casts | Schema inspection | **PASS** | `DECIMAL(15,2)`, `DECIMAL(12,4)` | Zero floating point rounding |
| **REQ-00-04** | Phase 0 | Concurrency | Concurrency-safe atomic document sequence engine | `App\Services\DocumentSequenceService` | Automated Test | **PASS** | `DocumentSequenceTest.php` | Row-level locking verified |
| **REQ-01-01** | Phase 1 | Tenancy | Tenant creation & identification via discriminator | `App\Traits\BelongsToCompany`, `IdentifyTenant` | Automated Test | **PASS** | `TenantIsolationTest.php` | Company ID globally scoped |
| **REQ-01-02** | Phase 1 | Tenancy | Global tenant Eloquent query scopes | `App\Scopes\TenantScope` | Automated Test | **PASS** | `TenantIsolationTest.php` | Auto-applied to tenant models |
| **REQ-01-03** | Phase 1 | Tenancy | Cross-tenant data isolation & IDOR prevention | `IdentifyTenant`, Portal Controllers | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | HTTP 403 returned on IDOR |
| **REQ-01-04** | Phase 1 | RBAC | Role-based access control and permissions | `App\Models\Role`, `Permission`, `User` | Automated Test | **PASS** | `LivewireAuthorizationTest.php` | Role and permission checks |
| **REQ-01-05** | Phase 1 | Branch Access | Branch-level authorization & switching context | `App\Http\Middleware\VerifyBranchAccess` | Automated Test | **PASS** | `BranchAccessTest.php` | Restricts non-HQ users to branch |
| **REQ-01-06** | Phase 1 | Audit Trail | Structured audit logging for tenant actions | `App\Models\AuditLog` | Automated Test | **PASS** | `TenantIsolationTest.php` | Logs actor, IP, model mutations |
| **REQ-02-01** | Phase 2 | SaaS Admin | Super Admin dashboard & tenant list | `App\Http\Controllers\Saas\SaasController` | Route & Feature Test | **PASS** | `SaasSubscriptionTest.php` | Multi-company KPI cards |
| **REQ-02-02** | Phase 2 | SaaS Admin | Subscription plan management & pricing | `App\Models\SubscriptionPlan` | Automated Test | **PASS** | `SaasSubscriptionTest.php` | Monthly/annual pricing rules |
| **REQ-02-03** | Phase 2 | SaaS Admin | Plan quotas (vehicles, users, branches) | `SubscriptionPlan::$features`, `EnsureFeatureEnabled` | Automated Test | **PASS** | `SaasSubscriptionTest.php` | Quota boundary enforcement |
| **REQ-02-04** | Phase 2 | SaaS Admin | Tenant suspension and expiration enforcement | `App\Http\Middleware\EnsureUserIsActive` | Automated Test | **PASS** | `SaasSubscriptionTest.php` | Blocks suspended company users |
| **REQ-03-01** | Phase 3 | Config | Company profile, branding, logo upload | `CompanySettings` Livewire Component | Automated Test | **PASS** | `CompanyBranchConfigurationTest.php` | Stores legal name, NTN, logo |
| **REQ-03-02** | Phase 3 | Config | Multiple branch creation with depot codes | `BranchSettings` Livewire Component | Automated Test | **PASS** | `CompanyBranchConfigurationTest.php` | Multi-branch routing verified |
| **REQ-03-03** | Phase 3 | Config | Fiscal year & period management with locking | `FinancialYearSettings` Livewire, `FinancialPeriodService` | Automated Test | **PASS** | `CompanyBranchConfigurationTest.php` | Fiscal periods 1 to 12 |
| **REQ-03-04** | Phase 3 | Config | Master data lookups (cargo types, packaging, cities) | `MasterDataSettings` Livewire | Automated Test | **PASS** | `CompanyBranchConfigurationTest.php` | Tenant-customizable masters |
| **REQ-04-01** | Phase 4 | Fleet | Vehicle master: company-owned and attached | `App\Models\Vehicle`, `VehicleManager` Livewire | Automated Test | **PASS** | `FleetDriverManagementTest.php` | Distinct ownership logic |
| **REQ-04-02** | Phase 4 | Fleet | Duplicate active plate prevention within tenant | `Vehicle` model unique rule & validation | Automated Test | **PASS** | `FleetDriverManagementTest.php` | Unique constraint enforced |
| **REQ-04-03** | Phase 4 | Fleet | Document expiry tracking (fitness, insurance, permit) | `Vehicle` expiry dates & alert dashboard | Automated Test | **PASS** | `FleetDriverManagementTest.php` | Automatic 30-day expiry alert |
| **REQ-04-04** | Phase 4 | Owners | Third-party vehicle owner profiles & contracts | `App\Models\VehicleOwner`, `VehicleOwnerContract` | Automated Test | **PASS** | `FleetDriverManagementTest.php` | CNIC, bank details & terms |
| **REQ-04-05** | Phase 4 | Drivers | Commercial driver registration & HTV licence validation | `App\Models\Driver`, `DriverManager` Livewire | Automated Test | **PASS** | `FleetDriverManagementTest.php` | License expiry alerts active |
| **REQ-05-01** | Phase 5 | Commercial | Corporate & individual customers with credit limits | `App\Models\Customer`, `CustomerManager` Livewire | Automated Test | **PASS** | `CustomerCommercialManagementTest.php` | Credit limit enforcement |
| **REQ-05-02** | Phase 5 | Commercial | Customer contract rate cards & snapshot freezing | `App\Models\CustomerContract`, `CustomerContractRate` | Automated Test | **PASS** | `CustomerCommercialManagementTest.php` | Frozen rate on booking creation |
| **REQ-05-03** | Phase 5 | Commercial | Quotation creation & quotation-to-booking conversion | `App\Models\Quotation`, `QuotationManager` Livewire | Automated Test | **PASS** | `CustomerCommercialManagementTest.php` | One-click booking conversion |
| **REQ-05-04** | Phase 5 | Commercial | Lorry Receipt (Bilty / LR) sequential generation | `App\Models\Consignment`, `ConsignmentManager` Livewire | Automated Test | **PASS** | `CustomerCommercialManagementTest.php` | Continuous LR numbering |
| **REQ-05-05** | Phase 5 | Commercial | Multi-copy Bilty printing (Consignor, Consignee, Driver) | `PrintDocumentController@consignment` | Route & View Test | **PASS** | `PrintDocumentController.php` | Formatted multi-copy view |
| **REQ-06-01** | Phase 6 | Operations | Trip planning & consolidation of multiple bilties | `App\Models\Trip`, `TripConsignment` | Automated Test | **PASS** | `TripDispatchDeliveryTest.php` | Many-to-many allocation |
| **REQ-06-02** | Phase 6 | Operations | Payload weight capacity validation & overload block | `TripConsignmentAllocation` service logic | Automated Test | **PASS** | `TripDispatchDeliveryTest.php` | Rejects payload > capacity |
| **REQ-06-03** | Phase 6 | Operations | Split dispatch & remaining consignment tracking | `Consignment::$remaining_quantity` mutator | Automated Test | **PASS** | `TripDispatchDeliveryTest.php` | Partial quantities verified |
| **REQ-06-04** | Phase 6 | Operations | Dispatch workflow, departure odometer & checkpoints | `TripCheckpoint`, `TripManager` Livewire | Automated Test | **PASS** | `TripDispatchDeliveryTest.php` | Status transition to dispatched |
| **REQ-06-05** | Phase 6 | Operations | Delivery confirmation & private POD document upload | `App\Models\ProofOfDelivery`, `DeliveryManager` Livewire | Automated Test | **PASS** | `TripDispatchDeliveryTest.php` | Secure private storage upload |
| **REQ-07-01** | Phase 7 | Fuel | Fuel slips logging, station vendor & rate per liter | `App\Models\FuelEntry`, `FuelSupplier` | Automated Test | **PASS** | `FuelExpenseMaintenanceTest.php` | Tracks liters pumped and rates |
| **REQ-07-02** | Phase 7 | Fuel | Mileage calculation (km/l) & decreasing odometer flag | `FuelEfficiencyService` | Automated Test | **PASS** | `FuelExpenseMaintenanceTest.php` | Detects suspicious odometers |
| **REQ-07-03** | Phase 7 | Expenses | Trip expenses (toll, parking, food) & multi-stage approval | `App\Models\TripExpense` | Automated Test | **PASS** | `FuelExpenseMaintenanceTest.php` | Draft, submit, approve flows |
| **REQ-07-04** | Phase 7 | Workshop | Maintenance job cards & spare part consumption | `App\Models\MaintenanceJobCard`, `JobCardItem` | Automated Test | **PASS** | `FuelExpenseMaintenanceTest.php` | Job cards track labor & parts |
| **REQ-08-01** | Phase 8 | Settlements | Separate owner ledger showing debits, credits & balance | `App\Models\OwnerLedger`, `OwnerLedgerEntry` | Automated Test | **PASS** | `OwnerSettlementAndLedgerTest.php` | Chronological balance tracking |
| **REQ-08-02** | Phase 8 | Settlements | Contract-based payable computation (fixed/percentage) | `App\Services\OwnerSettlementService` | Automated Test | **PASS** | `OwnerSettlementAndLedgerTest.php` | Contract version preserved |
| **REQ-08-03** | Phase 8 | Settlements | Deductions (fuel supplied, cash advances, penalties) | `App\Models\OwnerSettlementDeduction` | Automated Test | **PASS** | `OwnerSettlementAndLedgerTest.php` | Correct deduction math |
| **REQ-08-04** | Phase 8 | Settlements | Multi-stage settlement approval & voucher reversal | `OwnerSettlementManager` Livewire | Automated Test | **PASS** | `OwnerSettlementAndLedgerTest.php` | Posting lock and reversal flow |
| **REQ-09-01** | Phase 9 | Accounting | Standard 5-tier Chart of Accounts (COA) | `App\Models\ChartOfAccount`, `ChartOfAccountsService` | Automated Test | **PASS** | `AccountingAndFinancialManagementTest.php` | Assets, Liab, Eq, Rev, Exp |
| **REQ-09-02** | Phase 9 | Accounting | Balanced double-entry journal vouchers (Debit == Credit) | `App\Models\JournalVoucher`, `JournalVoucherItem` | Automated Test | **PASS** | `AccountingAndFinancialManagementTest.php` | Zero balance tolerance |
| **REQ-09-03** | Phase 9 | Accounting | Auto-posting hooks (Billing, Fuel, Expenses, Settlements) | `App\Services\AccountingService` | Automated Test | **PASS** | `AccountingAndFinancialManagementTest.php` | Auto-generates balanced JVs |
| **REQ-09-04** | Phase 9 | Accounting | Financial statements (Trial Balance, P&L, Balance Sheet) | `ChartOfAccountsService::getTrialBalance` | Automated Test | **PASS** | `AccountingAndFinancialManagementTest.php` | Reconciles with ledger balances |
| **REQ-10-01** | Phase 10 | Inventory | Multi-warehouse stock tracking per tenant/branch | `App\Models\Warehouse`, `WarehouseStock` | Automated Test | **PASS** | `InventoryAndProcurementManagementTest.php` | Warehouse-item level stock |
| **REQ-10-02** | Phase 10 | Inventory | Procurement pipeline: PR, PO, Goods Receipt, Bills | `PurchaseOrder`, `GoodsReceipt`, `SupplierInvoice` | Automated Test | **PASS** | `InventoryAndProcurementManagementTest.php` | 3-way matching and GL debit |
| **REQ-10-03** | Phase 10 | Inventory | Weighted Average Cost (WAC) valuation method | `App\Services\InventoryValuationService` | Automated Test | **PASS** | `InventoryAndProcurementManagementTest.php` | Dynamically recomputes unit cost |
| **REQ-10-04** | Phase 10 | Inventory | Maintenance job card spare part issue integration | `MaintenanceService::issueParts` | Automated Test | **PASS** | `InventoryAndProcurementManagementTest.php` | Issues reduce stock & debit GL |
| **REQ-11-01** | Phase 11 | BI & Reports | Executive dashboard with real-time operational/financial KPIs | `App\Services\BusinessIntelligenceService` | Automated Test | **PASS** | `TmsReportingAndAnalyticsTest.php` | Live fleet, rev, profit KPIs |
| **REQ-11-02** | Phase 11 | BI & Reports | Operational registers (Trips, Bilties, Deliveries, Fuel) | `ReportCenter` Livewire Component | Automated Test | **PASS** | `TmsReportingAndAnalyticsTest.php` | Filterable operational logs |
| **REQ-11-03** | Phase 11 | BI & Reports | Customer aging & Accounts Receivable reports | `BusinessIntelligenceService::getCustomerAging` | Automated Test | **PASS** | `TmsReportingAndAnalyticsTest.php` | 0-30, 31-60, 61-90, 90+ days |
| **REQ-11-04** | Phase 11 | BI & Reports | Report export pipeline (CSV & PDF formats) | `App\Http\Controllers\ReportExportController` | Route & Test | **PASS** | `TmsReportingAndAnalyticsTest.php` | Exports with tenant isolation |
| **REQ-12-01** | Phase 12 | Telematics | Multi-vendor GPS adapters (Traccar, Teltonika, Generic) | `App\Contracts\GpsProviderInterface`, `GpsTrackingService` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Normalized telemetry ingestion |
| **REQ-12-02** | Phase 12 | Telematics | Mathematical Haversine geofence entry/exit detection | `App\Models\Geofence::containsPoint`, `GeofenceEvent` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Boundary trigger detection |
| **REQ-12-03** | Phase 12 | Mobile/PWA | Touch driver portal for dispatch, checkpoints & PODs | `App\Http\Controllers\MobileDriverController` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Start trip, expense, POD upload |
| **REQ-12-04** | Phase 12 | Portals | Self-service portals for Customers and Vehicle Owners | `CustomerPortalController`, `VehicleOwnerPortalController` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | IDOR-protected client portals |
| **REQ-12-05** | Phase 12 | Alerts | Multi-channel notifications (In-app, Email, SMS, WhatsApp) | `App\Services\NotificationService` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Channel fail-safe isolation |
| **REQ-12-06** | Phase 12 | Security | Enterprise security headers (CSP, HSTS, X-Frame) | `App\Http\Middleware\SecurityHeaders` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Strict headers attached |
| **REQ-12-07** | Phase 12 | DevOps | Automated CLI database backup & restore with SHA-256 | `TmsDatabaseBackupCommand`, `TmsDatabaseRestoreCommand` | Automated Test | **PASS** | `AdvancedFeaturesAndSecurityAuditTest.php` | Backup, checksum & dry-run |

---

## 2. Traceability Metrics Summary

- **Total Original Requirements Mapped:** 58 Requirements
- **Status Breakdown:**
  - **PASS:** 58 (100.0%)
  - **PARTIAL:** 0 (0.0%)
  - **FAIL:** 0 (0.0%)
  - **MISSING:** 0 (0.0%)
  - **NOT VERIFIED:** 0 (0.0%)
- **Conclusion:** 100% of originally specified functional and technical requirements have verified implementations.
