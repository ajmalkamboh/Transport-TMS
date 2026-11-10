# GOODS TRANSPORT MANAGEMENT SYSTEM (TMS) — DEVELOPMENT DEPENDENCY MAP
**Document Reference:** TMS-DEP-001  
**Phase:** Phase 0 — Audit & Development Planning  
**Audit Date:** November 10, 2026  
**Status:** DEPENDENCY TOPOLOGY COMPLETE — AWAITING STAKEHOLDER APPROVAL  

---

## 1. Executive Summary

In a mission-critical Goods Transport Management System, operational modules are tightly coupled with financial and regulatory pipelines. Attempting to build transactional modules (e.g. Trips, Invoices, or Driver Advances) before foundational master data and accounting structures exist leads to severe architectural debt, database rework, and data corruption.

This document establishes the **Topological Dependency Graph** that governs the mandatory implementation sequence.

---

## 2. Topological Dependency Graph

```mermaid
flowchart TD
    subgraph Tier0 ["Tier 0: Infrastructure & Scaffolding"]
        T0_1["Laravel Framework & PHP 8.3 CLI"]
        T0_2["WAMP MySQL 8.4.7 Database Engine"]
        T0_3["Falcon v3.26.0 Bootstrap 5 Theme"]
        T0_4["Multi-Tenant Core (BelongsToCompany)"]
        T0_5["Branch Scoping (BelongsToBranch)"]
        T0_6["RBAC Engine (Roles & Permissions)"]
    end

    subgraph Tier1 ["Tier 1: Master Data Foundations"]
        T1_1["Chart of Accounts (COA Foundation)"]
        T1_2["Companies & Branches Master"]
        T1_3["Customers (Consignor & Consignee)"]
        T1_4["Vehicle Owners (Market Contractors)"]
        T1_5["Fleet & Vehicles Master"]
        T1_6["Drivers Master"]
        T1_7["Locations, Hubs & Routes"]
        T1_8["Freight Rate Cards & Contracts"]
    end

    subgraph Tier2 ["Tier 2: Booking & Consignment Engine"]
        T2_1["Atomic Sequence Generator"]
        T2_2["Freight Quotations"]
        T2_3["Customer Bookings"]
        T2_4["Consignment (Lorry Receipt / Bilty)"]
        T2_5["Multi-Copy Bilty Printing & Barcodes"]
    end

    subgraph Tier3 ["Tier 3: Trip Dispatch & Operations"]
        T3_1["Trip Planning & Load Manifest"]
        T3_2["Vehicle Capacity Verification"]
        T3_3["Trip Sheet Dispatch"]
        T3_4["Driver Cash Advances"]
        T3_5["Fuel Logs & Station Management"]
        T3_6["En-Route Trip Expenses"]
    end

    subgraph Tier4 ["Tier 4: Delivery, POD & Customer Billing"]
        T4_1["Destination Arrival & Offloading"]
        T4_2["Delivery Execution & Shortage Logs"]
        T4_3["Private POD Upload & Verification"]
        T4_4["Customer Freight Invoicing"]
        T4_5["Customer Payments & Receivables"]
    end

    subgraph Tier5 ["Tier 5: Accounting & Settlements"]
        T5_1["Double-Entry Accounting Service"]
        T5_2["Driver Trip Expense Reconciliation"]
        T5_3["Vehicle Owner Settlement Calculation"]
        T5_4["Owner Payments & Withholding Tax"]
        T5_5["General Ledger & Financial Statements"]
    end

    subgraph Tier6 ["Tier 6: Telematics, Inventory & Advanced Analytics"]
        T6_1["Fleet Maintenance & Work Orders"]
        T6_2["Spare Parts & Tire Inventory"]
        T6_3["GPS Telematics & Live Map Tracking"]
        T6_4["Notification Alerts (SMS/WhatsApp)"]
        T6_5["Vehicle Profitability & Executive KPIs"]
    end

    %% Dependencies between Tiers
    Tier0 --> Tier1
    T1_1 & T1_2 & T1_3 & T1_7 & T1_8 --> Tier2
    Tier2 & T1_4 & T1_5 & T1_6 --> Tier3
    Tier3 & T2_4 --> Tier4
    Tier4 & Tier3 & T1_1 --> Tier5
    Tier3 & Tier5 --> Tier6
```

---

## 3. Critical Architectural Coupling Analysis

### 3.1 Tenant & Branch Scoping Coupling
- **Prerequisite for:** Every single table, Eloquent query, and Livewire component.
- **Why it matters:** If `company_id` and `branch_id` traits are added retrospectively, hundreds of database queries and controller methods require refactoring to prevent severe cross-tenant data leaks.
- **Enforcement:** `BaseTenantModel` automatically applies `BelongsToCompany` global scope on `booted()`.

### 3.2 Fleet Ownership Model (Own vs Market Trucks)
- **Prerequisite for:** Trip Planning, Driver Advances, and Settlements.
- **Why it matters:** Transport operations use three distinct vehicle types:
  1. **Company-Owned Vehicles**: Depreciation, maintenance, fuel consumption, driver salary.
  2. **Dedicated / Attached Vehicles**: Fixed monthly or revenue-share agreement.
  3. **Market / Hired Trucks**: Per-trip hire cost paid to an external `VehicleOwner`. Requires advance deduction, diesel slips clearance, and net owner settlement vouchers.
- **Enforcement:** `ownership_type` column on `vehicles` determines the settlement and expense workflow.

### 3.3 Booking-to-Consignment Coupling
- **Prerequisite for:** Trip Manifests and Customer Billing.
- **Why it matters:** Quotations convert to Bookings, which generate legal **Consignments (Bilties)**. A Bilty is a legal contract of carriage and financial invoice precursor.
- **Enforcement:** An atomic sequence generator must assign immutable, consecutive Bilty numbers per company/financial year.

### 3.4 Trip Manifest & Dispatch Coupling
- **Prerequisite for:** Delivery, En-route Expenses, and POD.
- **Why it matters:** Multiple Consignments (LTL shipments) or a single bulk consignment (FTL) are bound to a single **Trip**. The Trip is the operational unit where driver advances and diesel slips are incurred.
- **Enforcement:** A Consignment cannot be dispatched on more than one active trip simultaneously.

### 3.5 Double-Entry Accounting Integration
- **Prerequisite for:** Financial statements, Customer balances, Owner balances, and Auditability.
- **Why it matters:** In logistics, operational actions are direct financial events:
  - *Bilty Issued (Paid/To-Pay/TBB)* -> Debit Accounts Receivable / Cash, Credit Freight Revenue.
  - *Driver Advance Disbursed* -> Debit Driver Advance (Asset), Credit Cash / Bank.
  - *Diesel Slip Issued* -> Debit Fuel Expense, Credit Fuel Vendor Payable.
  - *Market Vehicle Dispatched* -> Debit Truck Hire Expense, Credit Vehicle Owner Payable.
  - *Trip Reconciled* -> Offset Driver Advance against validated Trip Expenses; balance returned or reimbursed.
  - *Owner Settlement Finalized* -> Debit Owner Payable, Credit Advances, Credit Diesel, Credit Commission, Credit Bank.
- **Enforcement:** `AccountingService` encapsulates all journal creation inside database transactions, enforcing `sum(debits) === sum(credits)`.

---

## 4. Module Input-Output Data Flow Matrix

| Upstream Module | Generated Artifact / State | Consumed By Downstream Module | Downstream Action |
| :--- | :--- | :--- | :--- |
| **Customers & Contracts** | Agreed Rate Cards by Route & Weight Slab | **Quotations & Bookings** | Auto-calculates freight estimate. |
| **Bookings** | Verified Freight Cargo & Dimensions | **Consignment (Bilty)** | Generates official legal carriage document with atomic sequence number. |
| **Consignments** | Active Consignments staged at hub | **Trip Dispatch** | Aggregates consignments into vehicle manifest; verifies vehicle weight limit. |
| **Trip Dispatch** | Trip Sheet & Driver Cash Advance | **Trip Operations & Accounting** | Disburses advance voucher; opens en-route expense ledger. |
| **Trip Operations** | Diesel slips, Toll charges, Weighing receipts | **Trip Reconciliation** | Audits actual trip costs against driver advances. |
| **Trip Destination Arrival** | Offloaded Cargo Manifest | **Delivery & POD** | Triggers local delivery run or consignee warehouse offloading. |
| **Delivery Execution** | Signed Delivery Slip / Shortage Record | **POD Management** | Uploads scanned POD; updates consignment status to `Delivered`. |
| **POD Management** | Verified Scanned POD | **Customer Billing** | Unlocks consignment for invoicing; attaches POD to customer freight bill. |
| **Customer Billing** | Final Freight Invoice & Tax Breakdown | **Accounting (General Ledger)** | Posts Sales Invoice Journal Voucher; updates Customer Accounts Receivable. |
| **Trip Reconciliation** | Audited Trip Manifest & Expense Log | **Vehicle Owner Settlement** | Deducts advances & diesel from agreed truck hire freight; computes net payable. |
| **Owner Settlement** | Approved Owner Settlement Slip | **Accounting (General Ledger)** | Posts Settlement Journal Voucher; issues Bank Payment to Vehicle Owner. |

