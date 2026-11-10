<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the foundational application database.
     */
    public function run(): void
    {
        // 1. Create Core Permissions
        $permissions = [
            // SaaS Platform
            ['name' => 'Manage Companies', 'slug' => 'manage_companies', 'module' => 'saas'],
            ['name' => 'Manage Plans', 'slug' => 'manage_plans', 'module' => 'saas'],
            ['name' => 'View Platform Metrics', 'slug' => 'view_platform_metrics', 'module' => 'saas'],

            // Company & Branch Management
            ['name' => 'Manage Company Profile', 'slug' => 'manage_company_profile', 'module' => 'company'],
            ['name' => 'Manage Branches', 'slug' => 'manage_branches', 'module' => 'company'],
            ['name' => 'All Branches Access', 'slug' => 'all_branches_access', 'module' => 'company'],

            // User Management
            ['name' => 'View Users', 'slug' => 'view_users', 'module' => 'users'],
            ['name' => 'Create Users', 'slug' => 'create_users', 'module' => 'users'],
            ['name' => 'Edit Users', 'slug' => 'edit_users', 'module' => 'users'],
            ['name' => 'Delete Users', 'slug' => 'delete_users', 'module' => 'users'],
            ['name' => 'Manage Roles', 'slug' => 'manage_roles', 'module' => 'users'],

            // Consignments (LR / Bilty)
            ['name' => 'View Consignments', 'slug' => 'view_consignments', 'module' => 'consignments'],
            ['name' => 'Create Consignments', 'slug' => 'create_consignments', 'module' => 'consignments'],
            ['name' => 'Edit Consignments', 'slug' => 'edit_consignments', 'module' => 'consignments'],
            ['name' => 'Cancel Consignments', 'slug' => 'cancel_consignments', 'module' => 'consignments'],
            ['name' => 'Print Bilty', 'slug' => 'print_bilty', 'module' => 'consignments'],

            // Trip Dispatch & Operations
            ['name' => 'View Trips', 'slug' => 'view_trips', 'module' => 'trips'],
            ['name' => 'Create Trips', 'slug' => 'create_trips', 'module' => 'trips'],
            ['name' => 'Dispatch Trips', 'slug' => 'dispatch_trips', 'module' => 'trips'],
            ['name' => 'Manage Advances', 'slug' => 'manage_advances', 'module' => 'trips'],
            ['name' => 'Manage Fuel', 'slug' => 'manage_fuel', 'module' => 'trips'],

            // POD Management
            ['name' => 'Upload POD', 'slug' => 'upload_pod', 'module' => 'pod'],
            ['name' => 'Verify POD', 'slug' => 'verify_pod', 'module' => 'pod'],
            ['name' => 'Download POD', 'slug' => 'download_pod', 'module' => 'pod'],

            // Customer Billing & Invoicing
            ['name' => 'View Invoices', 'slug' => 'view_invoices', 'module' => 'billing'],
            ['name' => 'Create Invoices', 'slug' => 'create_invoices', 'module' => 'billing'],
            ['name' => 'Receive Payments', 'slug' => 'receive_payments', 'module' => 'billing'],

            // Accounting & Settlements
            ['name' => 'View Ledger', 'slug' => 'view_ledger', 'module' => 'accounting'],
            ['name' => 'Create Vouchers', 'slug' => 'create_vouchers', 'module' => 'accounting'],
            ['name' => 'Manage Owner Settlements', 'slug' => 'manage_owner_settlements', 'module' => 'accounting'],
            ['name' => 'View Financial Reports', 'slug' => 'view_reports', 'module' => 'accounting'],
        ];

        $permissionModels = [];
        foreach ($permissions as $p) {
            $permissionModels[$p['slug']] = Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // 2. Create System Roles
        $tenantAdminRole = Role::firstOrCreate(
            ['company_id' => null, 'slug' => 'tenant_admin'],
            [
                'name' => 'Tenant Owner / Administrator',
                'description' => 'Full administrative control over tenant company, fleet, branches, and books.',
                'is_system' => true,
            ]
        );

        $branchManagerRole = Role::firstOrCreate(
            ['company_id' => null, 'slug' => 'branch_manager'],
            [
                'name' => 'Branch Manager',
                'description' => 'Supervises branch dispatch operations, consignment bookings, and local cash flow.',
                'is_system' => true,
            ]
        );

        $bookingClerkRole = Role::firstOrCreate(
            ['company_id' => null, 'slug' => 'booking_clerk'],
            [
                'name' => 'Booking Clerk',
                'description' => 'Registers consignments (Bilties) and customer cargo pickups at local hubs.',
                'is_system' => true,
            ]
        );

        $dispatcherRole = Role::firstOrCreate(
            ['company_id' => null, 'slug' => 'dispatcher'],
            [
                'name' => 'Fleet Dispatcher',
                'description' => 'Consolidates consignments onto trip manifests, assigns trucks, and logs advances.',
                'is_system' => true,
            ]
        );

        $accountantRole = Role::firstOrCreate(
            ['company_id' => null, 'slug' => 'accountant'],
            [
                'name' => 'Financial Accountant',
                'description' => 'Audits freight invoices, customer collections, and market vehicle settlements.',
                'is_system' => true,
            ]
        );

        // Assign permissions to Tenant Admin (all non-saas permissions)
        $tenantPermIds = collect($permissionModels)
            ->filter(fn($p) => $p->module !== 'saas')
            ->pluck('id')
            ->toArray();
        $tenantAdminRole->permissions()->sync($tenantPermIds);

        // Assign permissions to Booking Clerk
        $bookingClerkPerms = collect($permissionModels)
            ->filter(fn($p) => in_array($p->slug, ['view_consignments', 'create_consignments', 'print_bilty']))
            ->pluck('id')
            ->toArray();
        $bookingClerkRole->permissions()->sync($bookingClerkPerms);

        // Assign permissions to Dispatcher
        $dispatcherPerms = collect($permissionModels)
            ->filter(fn($p) => in_array($p->slug, [
                'view_consignments', 'view_trips', 'create_trips', 'dispatch_trips', 'manage_advances', 'manage_fuel'
            ]))
            ->pluck('id')
            ->toArray();
        $dispatcherRole->permissions()->sync($dispatcherPerms);

        // 3. Create Platform Super Admin (company_id is null)
        User::firstOrCreate(
            ['email' => 'admin@tms.local'],
            [
                'name' => 'Platform Super Administrator',
                'password' => Hash::make('Password123!'),
                'is_super_admin' => true,
                'status' => 'active',
                'company_id' => null,
                'branch_id' => null,
            ]
        );

        // 4. Create Demo Tenant Company A
        $companyA = Company::firstOrCreate(
            ['code' => 'FEC'],
            [
                'name' => 'Falcon Express Cargo Ltd',
                'slug' => 'falcon-express',
                'email' => 'operations@falconexpress.com',
                'phone' => '+92-42-35889900',
                'address' => 'Plot 42, Logistics Park, Multan Road, Lahore',
                'tax_number' => 'NTN-4018294-1',
                'status' => 'active',
                'subscription_plan' => 'enterprise',
                'subscription_expires_at' => now()->addYear(),
                'settings' => [
                    'bilty_prefix' => 'FEC-LR',
                    'trip_prefix' => 'FEC-TR',
                    'invoice_prefix' => 'FEC-INV',
                    'currency' => 'PKR',
                ],
            ]
        );

        // Branches for Company A
        $branchLahore = Branch::firstOrCreate(
            ['company_id' => $companyA->id, 'code' => 'FEC-LHR'],
            [
                'name' => 'Lahore Central Terminal',
                'city' => 'Lahore',
                'phone' => '+92-42-35889901',
                'email' => 'lahore@falconexpress.com',
                'address' => 'Main Multan Road Hub, Lahore',
                'is_head_office' => true,
                'is_active' => true,
            ]
        );

        $branchKarachi = Branch::firstOrCreate(
            ['company_id' => $companyA->id, 'code' => 'FEC-KHI'],
            [
                'name' => 'Karachi Port Terminal',
                'city' => 'Karachi',
                'phone' => '+92-21-32448800',
                'email' => 'karachi@falconexpress.com',
                'address' => 'West Wharf Road, Port Area, Karachi',
                'is_head_office' => false,
                'is_active' => true,
            ]
        );

        // Users for Company A
        $ownerA = User::firstOrCreate(
            ['email' => 'owner@falconexpress.com'],
            [
                'company_id' => $companyA->id,
                'branch_id' => $branchLahore->id,
                'role_id' => $tenantAdminRole->id,
                'name' => 'Malik Tariq (Managing Director)',
                'password' => Hash::make('Password123!'),
                'phone' => '+92-300-8451234',
                'status' => 'active',
                'is_super_admin' => false,
            ]
        );

        $bookingClerkA = User::firstOrCreate(
            ['email' => 'booking.lhr@falconexpress.com'],
            [
                'company_id' => $companyA->id,
                'branch_id' => $branchLahore->id,
                'role_id' => $bookingClerkRole->id,
                'name' => 'Muhammad Asif (Booking Officer)',
                'password' => Hash::make('Password123!'),
                'phone' => '+92-321-4567890',
                'status' => 'active',
                'is_super_admin' => false,
            ]
        );

        $dispatcherA = User::firstOrCreate(
            ['email' => 'dispatcher@falconexpress.com'],
            [
                'company_id' => $companyA->id,
                'branch_id' => $branchLahore->id,
                'role_id' => $dispatcherRole->id,
                'name' => 'Khurram Shehzad (Regional Dispatcher)',
                'password' => Hash::make('Password123!'),
                'phone' => '+92-333-7890123',
                'status' => 'active',
                'is_super_admin' => false,
            ]
        );

        // Authorize dispatcher for both Lahore and Karachi branches
        $dispatcherA->branches()->syncWithoutDetaching([$branchLahore->id, $branchKarachi->id]);

        // Document Sequences for Company A
        DocumentSequence::firstOrCreate(
            ['company_id' => $companyA->id, 'document_type' => 'consignment', 'year' => date('Y')],
            ['prefix' => 'FEC-LR', 'last_number' => 100]
        );
        DocumentSequence::firstOrCreate(
            ['company_id' => $companyA->id, 'document_type' => 'trip', 'year' => date('Y')],
            ['prefix' => 'FEC-TR', 'last_number' => 50]
        );

        // 5. Create Demo Tenant Company B (to test isolation)
        $companyB = Company::firstOrCreate(
            ['code' => 'IFL'],
            [
                'name' => 'Indus Freight Logistics',
                'slug' => 'indus-freight',
                'email' => 'contact@indusfreight.com',
                'phone' => '+92-51-2233445',
                'address' => 'I-9 Industrial Area, Islamabad',
                'tax_number' => 'NTN-5819203-2',
                'status' => 'active',
                'subscription_plan' => 'professional',
                'subscription_expires_at' => now()->addMonths(6),
                'settings' => [
                    'bilty_prefix' => 'IFL-LR',
                    'trip_prefix' => 'IFL-TR',
                    'invoice_prefix' => 'IFL-INV',
                    'currency' => 'PKR',
                ],
            ]
        );

        $branchIslamabad = Branch::firstOrCreate(
            ['company_id' => $companyB->id, 'code' => 'IFL-ISB'],
            [
                'name' => 'Islamabad Freight Depot',
                'city' => 'Islamabad',
                'phone' => '+92-51-2233446',
                'email' => 'islamabad@indusfreight.com',
                'address' => 'Sector I-9/2, Islamabad',
                'is_head_office' => true,
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'owner@indusfreight.com'],
            [
                'company_id' => $companyB->id,
                'branch_id' => $branchIslamabad->id,
                'role_id' => $tenantAdminRole->id,
                'name' => 'Chaudhry Nadeem (Owner)',
                'password' => Hash::make('Password123!'),
                'phone' => '+92-301-5556677',
                'status' => 'active',
                'is_super_admin' => false,
            ]
        );

        DocumentSequence::firstOrCreate(
            ['company_id' => $companyB->id, 'document_type' => 'consignment', 'year' => date('Y')],
            ['prefix' => 'IFL-LR', 'last_number' => 200]
        );
    }
}

