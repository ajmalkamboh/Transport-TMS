# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — SECURITY AUDIT & THREAT MODEL
**Document Reference:** TMS-SEC-001  
**Phase:** Phase 0 — Audit & Development Planning  
**Audit Date:** November 10, 2026  
**Status:** THREAT MODEL & SECURITY BASELINE ESTABLISHED — AWAITING IMPLEMENTATION  

---

## 1. Executive Summary & Security Baseline

Transport Management Systems handle highly sensitive commercial, logistical, and financial data: customer client lists, negotiated freight rates, driver identification documents, freight manifests, and financial general ledgers. Cross-tenant leakage between competing transport companies represents a catastrophic business and legal failure.

This Security Audit establishes the **Threat Model**, **Attack Surface**, and **Non-Negotiable Security Controls** required across all controllers, Livewire components, Eloquent models, and file storage pipelines.

---

## 2. Threat Vector Analysis & Mitigation Matrix

| Security Vector | Specific TMS Risk Scenario | Vulnerability Mechanism | Mandatory Architectural Mitigation |
| :--- | :--- | :--- | :--- |
| **Tenant Isolation (Multi-Tenancy)** | Company B accessing Company A's customer freight rates or bilties | Missing tenant filter in Eloquent queries or Livewire components | Implement `BelongsToCompany` trait with an automatic `addGlobalScope` restricting queries to `company_id = auth()->user()->company_id`. |
| **Branch Isolation** | Booking clerk at Branch 1 viewing or modifying dispatch queues of Branch 2 | Direct URL access or IDOR via unvalidated `branch_id` parameter | Implement `BelongsToBranch` trait and middleware ensuring users can only read/write records for their assigned branch(es). |
| **Insecure Direct Object Reference (IDOR)** | Attacker increments Bilty ID (`/consignments/1042/print`) to view competitor shipments | Route Model Binding without tenant-ownership verification | Explicit Laravel Route Model Binding scoping: `Route::bind('consignment', fn($id) => Consignment::where('id', $id)->firstOrFail())` governed by global scope, plus Policy checks. |
| **Livewire Action Hijacking** | Malicious user calls Livewire action `approveSettlement(12)` via browser console | Authorizing only in `render()` or `mount()`, omitting check in mutation method | Mandatory `$this->authorize('approve', $this->settlement)` inside every public action method (`dispatch()`, `delete()`, `approve()`). |
| **File Storage Exposure** | Publicly accessible Proof of Delivery (POD) scans or Driver CNIC images | Uploading PODs directly to `public/uploads/` accessible without auth | Store PODs and documents in `storage/app/private/companies/{company_id}/`. Restrict access via signed URLs or an authorized download controller verifying user permissions. |
| **Mass Assignment** | User injects `company_id` or `status='approved'` in request payload | Using `$guarded = []` in Eloquent models | Explicit `$fillable` array defined on every model; never mass-assign `company_id`, `status`, `approved_by`, or `balance`. |
| **SQL Injection** | Dynamic SQL in custom freight reports or search filters | Raw SQL concatenation (`DB::raw("WHERE city = '$city'")`) | Mandatory use of Eloquent query builder parameter bindings (`where('city', $city)` or `DB::raw('city = ?', [$city])`). |
| **Financial Entry Tampering** | Manual voucher creation resulting in unbalanced ledgers | Bypassing the double-entry accounting service | Encapsulate all journal mutations inside `AccountingService::createJournalEntry()`, enforcing `total_debit == total_credit` in a DB transaction. |
| **Document Sequence Collision** | Two operators generating the same Consignment or Invoice number simultaneously | Race condition in `Model::max('number') + 1` | Use pessimistic locking (`DB::table('document_sequences')->where(...)->lockForUpdate()`) to issue atomic document numbers. |
| **Audit Trail Absence** | Unauthorized modification or deletion of freight charges undetected | No historical record of field changes | Automated `AuditLogService` recording `user_id`, `company_id`, `event` (created/updated/deleted), `old_values`, `new_values`, and `ip_address`. |

---

## 3. Livewire Component Security Architecture

Livewire components represent the primary interactive surface of this application. Because Livewire exposes public methods to client-side requests, the following rules must be enforced across all Livewire classes:

```php
namespace App\Livewire\Consignments;

use Livewire\Component;
use App\Models\Consignment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ConsignmentForm extends Component
{
    use AuthorizesRequests;

    public Consignment $consignment;

    public function mount(?int $id = null)
    {
        if ($id) {
            // Global scope ensures this fails if belonging to another company
            $this->consignment = Consignment::findOrFail($id);
            $this->authorize('update', $this->consignment);
        } else {
            $this->consignment = new Consignment();
            $this->authorize('create', Consignment::class);
        }
    }

    public function save()
    {
        // Re-authorize before mutating state
        $this->authorize($this->consignment->exists ? 'update' : 'create', $this->consignment);
        
        $this->validate();

        // Enforce tenant ID strictly from authenticated user context, NEVER from user input
        $this->consignment->company_id = auth()->user()->company_id;
        $this->consignment->branch_id = auth()->user()->branch_id;
        $this->consignment->save();
    }
}
```

---

## 4. POD & Document Upload Security Specifications

Proof of Delivery (POD) documents contain customer signatures and delivery receipts. Scanned driver licenses and vehicle registration books contain national identity data (CNIC).

### 4.1 Storage Architecture
1. **Disallowed**: Storing uploads under `public/storage/` or `public/uploads/`.
2. **Mandatory**: Private storage disk configured in `config/filesystems.php`:
   - Path format: `private/companies/{company_id}/documents/{year}/{month}/{hash}.{ext}`.
3. **MIME-Type & Extension Whitelist**:
   - Allowed: `image/jpeg` (`.jpg`, `.jpeg`), `image/png` (`.png`), `application/pdf` (`.pdf`).
   - Disallowed: All executable extensions (`.php`, `.phtml`, `.exe`, `.sh`, `.js`).
   - Max file size: 5MB per document.

### 4.2 Authorized Download Gateway
All document downloads and POD previews must pass through an authorized controller route:
```php
Route::get('/companies/{company}/consignments/{consignment}/pod/download', [PodDownloadController::class, 'download'])
    ->middleware(['auth', 'tenant.verify'])
    ->name('consignments.pod.download');
```
The controller must verify:
- `auth()->user()->company_id === $consignment->company_id`
- User has permission `view_pod` or `view_consignment`.

---

## 5. Audit Logging Specifications

An immutable audit trail must be recorded in the `audit_logs` table:

```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    auditable_type VARCHAR(255) NOT NULL,
    auditable_id BIGINT UNSIGNED NOT NULL,
    action ENUM('created', 'updated', 'deleted', 'printed', 'status_changed') NOT NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_auditable (company_id, auditable_type, auditable_id),
    INDEX idx_company_user (company_id, user_id),
    CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

