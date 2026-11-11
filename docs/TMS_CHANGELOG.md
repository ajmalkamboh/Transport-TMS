# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — CHANGELOG
**Document Reference:** TMS-CHG-001  
**Repository:** https://github.com/ajmalkamboh/Transport-TMS.git  

All notable changes, module additions, architectural updates, and bug fixes for the Transport Management System (TMS) are documented in this file.

---

## [Phase 12] - 2026-11-11: Advanced Features, Security Audit & Production Deployment

### Added
- **GPS Telematics Engine & Provider Adapters**:
  - `App\Contracts\GpsProviderInterface`: Extensible contract for telematics providers.
  - `TraccarGpsProvider`, `TeltonikaGpsProvider`, `GenericGpsProvider` provider implementations.
  - `GpsTrackingService`: Raw telemetry ingestion, velocity tracking, heading, stop detection, and route deviation alerts.
  - Mathematical Haversine geofence containment engine with entry/exit event triggering (`Geofence`, `GeofenceEvent`).
- **Mobile Driver PWA & Touch Portal**:
  - `manifest.json` and Service Worker (`sw.js`) for mobile offline capability and installability.
  - Responsive mobile driver layout (`resources/views/layouts/mobile.blade.php`).
  - Mobile trip dashboard, start trip with departure odometer, en-route checkpoint recording, expense submission with receipts, delivery confirmation, and verified POD photo uploads (`MobileDriverController`).
  - Background HTML5 Geolocation synchronization endpoint (`/mobile/driver/location`).
- **Multi-Channel Notification Service**:
  - `NotificationService`: Configurable multi-channel alert delivery across In-App, Email, SMS, and WhatsApp.
  - Tenant-level notification preferences (`NotificationSetting`) with channel isolation to prevent third-party gateway timeouts from aborting transactions.
  - Audit logging of all outbound notifications (`NotificationLog`).
- **Customer Self-Service Portal**:
  - `CustomerPortalController`: Dashboard, consignment tracking list, real-time trip GPS tracking, invoices, and secure POD document download with IDOR authorization checks.
- **Attached Vehicle Owner Portal**:
  - `VehicleOwnerPortalController`: Dashboard, attached fleet list, trip history, settlement statements, and deduction breakdowns.
  - Views: `dashboard.blade.php`, `vehicles.blade.php`, `trips.blade.php`, `settlements.blade.php`, `settlement-detail.blade.php`.
- **Security Hardening**:
  - `SecurityHeaders` middleware: Enforces Content Security Policy (CSP), Strict-Transport-Security (HSTS), X-Frame-Options, X-Content-Type-Options, and Permissions-Policy.
  - `/health` endpoint: Monitoring probe reporting database, cache, storage, and PHP health metrics.
- **Disaster Recovery Backup Engine**:
  - `tms:backup-db`: Automated CLI backup generator supporting SQLite/MySQL with SHA-256 checksum calculation and 30-day retention cleanup.
  - `tms:restore-db`: Database restoration CLI command with SHA-256 integrity verification and `--dry-run` pre-flight analysis.
- **Production Deployment Configuration**:
  - `.env.production.example` template with secure defaults.
  - `deploy/supervisor/tms-worker.conf` configuration for queue workers.
  - `deploy/nginx/tms.conf` optimized web server configuration.
- **Automated Verification Suites**:
  - `tests/Feature/AdvancedFeaturesAndSecurityAuditTest.php` (8 tests, 66 assertions).
  - `tests/Feature/EndToEndProductionAcceptanceTest.php` (25-step production acceptance lifecycle test, 30 assertions).

### Fixed & Audited
- Added `deductions(): HasMany` relationship alias on `OwnerSettlement` model to ensure seamless resolution from portal controllers.
- Created missing views `portal.owner.trips` and `portal.owner.settlement-detail`.
- Linked settlement identifiers to detail views in `settlements.blade.php`.
- Added `/storage/app/backups/*` to `.gitignore` to prevent temporary test database dumps from contaminating the repository.

---

## [Phase 11] - 2026-11-11: Reporting, Dashboards & Business Intelligence

### Added
- `BusinessIntelligenceService`: Analytical computation engine for executive metrics, fleet utilization, driver performance, customer aging/ledgers, trip profitability, and financial statements.
- `ReportCenter` Livewire component: Filterable interactive analytical reports with tenant and branch scoping.
- Export controllers for PDF and CSV generation across transactional and analytical ledgers.

---

## [Phase 10] - 2026-11-11: Inventory & Procurement Management

### Added
- Multi-warehouse stock tracking per tenant and branch.
- Spare parts catalog, categories, units of measurement, and reorder levels.
- Procurement workflow: Requisitions, Purchase Orders, Goods Receipt Notes (GRN), and Supplier Invoices.
- Weighted Average Cost (WAC) valuation method.
- Workshop maintenance integration: Spare parts issued against vehicle job cards with automatic stock and double-entry GL ledger updates.

---

## [Phase 09] - 2026-11-11: Complete Accounting & Financial Management

### Added
- Standard transport Chart of Accounts (COA) with account groups, types, and tenant-level defaults.
- Financial Year and Periods engine with locking mechanism.
- Atomic Journal Voucher engine (`JournalVoucher`, `JournalVoucherItem`) with strict double-entry rule validation (Debit == Credit).
- Automated operational posting hooks: Consignment billing, fuel purchases, en-route trip expenses, owner settlements, and customer payments.

---

## [Phase 08] - 2026-11-11: Attached Vehicle Owner Settlements

### Added
- Third-party / attached vehicle owner ledger tracking opening balance, trip payables, deductions, advances, payments, and closing balance.
- Owner settlement preparation based on active contracts (fixed freight, percentage, per-km).
- Deductions management for fuel supplied, cash advances, and maintenance charges.
- Multi-stage settlement approval workflow: draft, submitted, approved, posted, and reversed.

---

## [Phase 07] - 2026-11-10: Fuel, Trip Expenses & Vehicle Maintenance

### Added
- Fuel management with fuel stations, fuel slips, rate per liter, and payment methods.
- Fuel efficiency tracking: Distance travelled, fuel consumed, average km/l, and flag for suspicious decreasing odometer readings.
- En-route trip expenses (toll taxes, driver allowances, parking, loading charges) with receipt attachments.
- Workshop maintenance job cards and preventive maintenance schedules.

---

## [Phase 06] - 2026-11-10: Trip Planning, Dispatch & Delivery Management

### Added
- Operational trip sheet management (`Trip`, `TripResource`, `TripConsignment`, `TripCheckpoint`).
- Multi-consignment consolidation on a single trip manifest with weight and volume validation.
- Vehicle and driver assignment conflict detection.
- Loading management, departure check-in, en-route checkpoints, and arrival logging.
- Delivery recording with shortage/damage notations and Proof of Delivery (POD) attachments.

---

## [Phase 05] - 2026-11-10: Customer & Commercial Management

### Added
- Customer masters (individual and corporate), credit limits, and payment terms.
- Route-based freight rate cards with contract rate freezing at the time of booking.
- Commercial quotations and quotation-to-booking conversion workflow.
- Legal Lorry Receipt / Bilty creation with atomic sequential document numbering.

---

## [Phase 04] - 2026-11-10: Fleet, Vehicle Owner & Driver Management

### Added
- Fleet master for company-owned and attached third-party vehicles.
- Duplicate active vehicle registration plate prevention within tenant scope.
- Third-party vehicle owner profiles and banking details with sensitive information protection.
- Commercial driver registration, license expiry alerts, and biometric/emergency contact records.

---

## [Phase 01 - 03] - 2026-11-10: SaaS Architecture, Multi-Tenancy & Access Control

### Added
- Super Admin management, company registration, and subscription plan quotas.
- Multi-branch depot hierarchy with branch codes and localized operational settings.
- Role-Based Access Control (RBAC) with granular permissions.
- Multi-tenant data isolation via `BelongsToCompany` trait and global query scopes.
