# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — DEFECTS & REMEDIATION PLAN

**Document Reference:** TMS-DEFECTS-001  
**Audit Date:** November 11, 2026  
**Auditor:** Quality Assurance Lead & DevOps Engineer  
**Classification Standards:**
- **CRITICAL:** Cross-tenant leakage, data loss, unbalanced vouchers, financial corruption.
- **HIGH:** Major workflow failure, broken authorization, billing/settlement calculation errors.
- **MEDIUM:** Incomplete configuration, environment limitations, missing links/advisories.
- **LOW:** Minor cosmetic issues or environment notices.

---

## 1. COMPREHENSIVE DEFECT LOG & AUDIT FINDINGS

### Finding DEF-01: Missing Vehicle Owner Portal Views (Trips & Settlement Details)
- **Severity:** HIGH (Resolved during Phase 12 Audit)
- **Module:** Vehicle Owner Self-Service Portal
- **Exact Location:** `resources/views/portal/owner/trips.blade.php`, `resources/views/portal/owner/settlement-detail.blade.php`
- **Description:** Route definitions in `routes/web.php` pointed to `VehicleOwnerPortalController@trips` and `@settlementDetail`, but the corresponding Blade view files were omitted from the initial commit.
- **Reproduction Steps:** Authenticate as vehicle owner user and navigate to `/portal/owner/trips` or `/portal/owner/settlements/{id}`.
- **Expected Result:** Renders trip history table and itemized deduction breakdown view.
- **Actual Result (Before Fix):** Threw `InvalidArgumentException: View [portal.owner.trips] not found`.
- **Business Impact:** Vehicle owners could not inspect trip histories or review itemized deduction lines.
- **Resolution & Evidence:** Created both responsive Blade views and added automated route assertions in `AdvancedFeaturesAndSecurityAuditTest.php` (Line 543). Tested and verified (HTTP 200 OK).

---

### Finding DEF-02: Undefined `deductions` Eloquent Relationship on OwnerSettlement Model
- **Severity:** HIGH (Resolved during Phase 12 Audit)
- **Module:** Attached Vehicle Owner Settlements
- **Exact Location:** `app/Models/OwnerSettlement.php` (Line 129), `app/Http/Controllers/VehicleOwnerPortalController.php` (Line 147)
- **Description:** The portal controller attempted to eager-load `deductions` (`$settlement->load(['deductions'])`), whereas the model defined the relationship as `deductionItems()`.
- **Reproduction Steps:** Access owner settlement detail route `/portal/owner/settlements/1`.
- **Expected Result:** Deductions eager-loaded without error.
- **Actual Result (Before Fix):** Threw `RelationNotFoundException: Call to undefined relationship [deductions] on model [App\Models\OwnerSettlement]`.
- **Business Impact:** Crashed the settlement detail screen whenever a vehicle owner opened a settlement.
- **Resolution & Evidence:** Added `public function deductions(): HasMany { return $this->deductionItems(); }` alias to `OwnerSettlement.php`. Tested and verified via `test_vehicle_owner_portal_and_settlements` (Line 487).

---

### Finding DEF-03: Storage Symlink Not Linked on Local Development Host
- **Severity:** MEDIUM (Operational / Configuration)
- **Module:** Public File Gateway & Document Serving
- **Exact Location:** `C:\wamp64\www\TMS\public\storage`
- **Description:** `php artisan about` reports `C:\wamp64\www\TMS\public\storage .. NOT LINKED`. The symbolic link from `public/storage` to `storage/app/public` has not been generated on the host machine.
- **Reproduction Steps:** Run `php artisan about` and inspect the Storage section.
- **Expected Result:** `public/storage` should be linked to `storage/app/public`.
- **Actual Result:** Unlinked.
- **Business Impact:** Publicly accessible assets (such as customer avatars or public company logos) will not resolve over HTTP until symlinked. Private documents (PODs, bilties) are served via `DocumentDownloadController` and are unaffected.
- **Remediation Plan:**
  ```powershell
  php artisan storage:link
  ```
- **Priority:** High Priority for Production Deployment.

---

### Finding DEF-04: Upstream Framework Vendor Security Advisories
- **Severity:** MEDIUM (Upstream Dependency Hardening)
- **Module:** Composer Vendor Dependencies
- **Exact Location:** `vendor/laravel/framework`, `vendor/symfony/*`, `vendor/league/commonmark`
- **Description:** `composer audit` reports 15 security advisories in installed vendor packages (e.g., CVE-2026-114509 in `laravel/framework < 13.27.0`).
- **Reproduction Steps:** Run `composer audit` in project root.
- **Expected Result:** Zero security advisories.
- **Actual Result:** 15 advisories reported in vendor dependencies.
- **Business Impact:** Potential risk of upstream framework exploits if vulnerable vendor endpoints are exposed. (Note: TMS uses session authentication and does not expose vulnerable TokenGuard API endpoints).
- **Remediation Plan:**
  Update core dependencies to patched minor releases prior to live Internet deployment:
  ```powershell
  composer update laravel/framework symfony/http-kernel symfony/mime league/commonmark
  ```
- **Priority:** Medium Priority prior to Public Production Launch.

---

### Finding DEF-05: WAMP MySQL Database Service Offline on Port 3306
- **Severity:** LOW (Host Environment Status)
- **Module:** Local Development Environment
- **Exact Location:** `127.0.0.1:3306` (WAMP Server MySQL Service)
- **Description:** Running `php artisan migrate:status` failed with connection refused because the local WAMP MySQL daemon was not running during the audit turn. Tests successfully ran against SQLite in-memory.
- **Reproduction Steps:** Execute `php artisan migrate:status`.
- **Expected Result:** Connects to MySQL and prints migration status.
- **Actual Result:** `SQLSTATE[HY000] [2002] No connection could be made because the target machine actively refused it`.
- **Business Impact:** Local interactive browser testing requires starting WAMP MySQL service. Automated CI/CD test suites run on SQLite and are unaffected.
- **Remediation Plan:**
  Start MySQL service in WampServer dashboard (`wampmanager.exe -> MySQL -> Start Service`) or ensure MySQL 8.0+ container is running on port 3306.
- **Priority:** Low Priority (Host administrative action).

---

## 2. DEFECT SUMMARY & REMEDIATION ROADMAP

| ID | Finding Description | Severity | Status | Action Required | Responsible |
| :---: | :--- | :---: | :---: | :--- | :---: |
| **DEF-01** | Missing Owner Portal Views | HIGH | **FIXED** | Verified in test suite | Completed |
| **DEF-02** | Undefined `deductions` Relation | HIGH | **FIXED** | Added alias in model | Completed |
| **DEF-03** | Storage Symlink Unlinked | MEDIUM | **OPEN** | Run `php artisan storage:link` | DevOps / Deployer |
| **DEF-04** | Upstream Vendor Advisories | MEDIUM | **OPEN** | Run `composer update` | Lead Developer |
| **DEF-05** | Local MySQL Service Stopped | LOW | **OPEN** | Start WAMP MySQL service | Local Operator |

**Defect Audit Conclusion:** Zero Critical and Zero High defects remain in the codebase. All remaining findings are operational or environmental configurations that are addressed during production server provisioning.

