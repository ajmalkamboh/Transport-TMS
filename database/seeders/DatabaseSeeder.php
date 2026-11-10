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

        // 3. Create Default Subscription Plans
        $starterPlan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter Tier',
                'description' => 'Ideal for small local transport operations with up to 5 trucks.',
                'monthly_price' => 15000.00,
                'quarterly_price' => 42000.00,
                'annual_price' => 150000.00,
                'trial_duration_days' => 14,
                'max_vehicles' => 5,
                'max_users' => 3,
                'max_branches' => 1,
                'max_storage_mb' => 2048,
                'features' => [
                    'fleet_management',
                    'driver_management',
                    'customer_management',
                    'booking_management',
                    'trip_management',
                ],
                'is_active' => true,
            ]
        );

        $professionalPlan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['slug' => 'professional'],
            [
                'name' => 'Professional Logistics Tier',
                'description' => 'Comprehensive freight and fleet control with fuel and double-entry accounting.',
                'monthly_price' => 35000.00,
                'quarterly_price' => 99000.00,
                'annual_price' => 350000.00,
                'trial_duration_days' => 14,
                'max_vehicles' => 25,
                'max_users' => 10,
                'max_branches' => 5,
                'max_storage_mb' => 10240,
                'features' => [
                    'fleet_management',
                    'driver_management',
                    'customer_management',
                    'booking_management',
                    'trip_management',
                    'fuel_management',
                    'maintenance',
                    'accounting',
                    'advanced_reporting',
                ],
                'is_active' => true,
            ]
        );

        $enterprisePlan = \App\Models\SubscriptionPlan::firstOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise Multi-Branch Tier',
                'description' => 'Unlimited fleet scale, real-time GPS tracking, customer tracking portal, and mobile app.',
                'monthly_price' => 75000.00,
                'quarterly_price' => 210000.00,
                'annual_price' => 750000.00,
                'trial_duration_days' => 14,
                'max_vehicles' => -1, // Unlimited
                'max_users' => -1,
                'max_branches' => -1,
                'max_storage_mb' => -1,
                'features' => array_keys(\App\Models\SubscriptionPlan::ALL_FEATURES),
                'is_active' => true,
            ]
        );

        // 4. Create Platform Super Admin (company_id is null)
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

        // Link Owner & Plan to Company A
        $companyA->owner_id = $ownerA->id;
        $companyA->plan_id = $enterprisePlan->id;
        $companyA->save();

        // Create Active Subscription for Company A
        $subA = \App\Models\Subscription::firstOrCreate(
            ['company_id' => $companyA->id, 'plan_id' => $enterprisePlan->id],
            [
                'billing_cycle' => 'annual',
                'status' => 'active',
                'starts_at' => now()->subMonth(),
                'ends_at' => now()->addMonths(11),
                'grace_period_ends_at' => now()->addMonths(11)->addDays(7),
                'price' => $enterprisePlan->annual_price,
                'currency' => 'PKR',
                'auto_renew' => true,
            ]
        );

        // Seed paid invoice and payment for Company A
        $invA = \App\Models\SubscriptionInvoice::firstOrCreate(
            ['company_id' => $companyA->id, 'invoice_number' => 'SAAS-INV-2026-0001'],
            [
                'subscription_id' => $subA->id,
                'invoice_date' => now()->subMonth()->toDateString(),
                'due_date' => now()->subDays(15)->toDateString(),
                'billing_cycle' => 'annual',
                'subtotal' => $enterprisePlan->annual_price,
                'tax_amount' => round($enterprisePlan->annual_price * 0.05, 2),
                'total_amount' => round($enterprisePlan->annual_price * 1.05, 2),
                'paid_amount' => round($enterprisePlan->annual_price * 1.05, 2),
                'balance_amount' => 0.00,
                'status' => 'paid',
                'notes' => 'Annual enterprise license fee paid via direct wire transfer.',
            ]
        );

        \App\Models\SubscriptionPayment::firstOrCreate(
            ['company_id' => $companyA->id, 'payment_number' => 'SAAS-PAY-2026-0001'],
            [
                'invoice_id' => $invA->id,
                'payment_date' => now()->subDays(20)->toDateString(),
                'amount' => $invA->total_amount,
                'payment_method' => 'bank_transfer',
                'payment_reference' => 'HBL-FT-94028471',
                'status' => 'verified',
                'verified_by' => 1,
                'verified_at' => now()->subDays(20),
            ]
        );

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

        $ownerB = User::firstOrCreate(
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

        $companyB->owner_id = $ownerB->id;
        $companyB->plan_id = $professionalPlan->id;
        $companyB->save();

        // Create Trial Subscription for Company B
        \App\Models\Subscription::firstOrCreate(
            ['company_id' => $companyB->id, 'plan_id' => $professionalPlan->id],
            [
                'billing_cycle' => 'monthly',
                'status' => 'trial',
                'starts_at' => now(),
                'ends_at' => now()->addDays(14),
                'grace_period_ends_at' => now()->addDays(17),
                'price' => $professionalPlan->monthly_price,
                'currency' => 'PKR',
                'auto_renew' => false,
            ]
        );

        DocumentSequence::firstOrCreate(
            ['company_id' => $companyB->id, 'document_type' => 'consignment', 'year' => date('Y')],
            ['prefix' => 'IFL-LR', 'last_number' => 200]
        );
    }
}

