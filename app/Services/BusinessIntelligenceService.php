<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\CustomerPayment;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\FuelEntry;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\JournalVoucher;
use App\Models\JournalVoucherItem;
use App\Models\OwnerSettlement;
use App\Models\ProofOfDelivery;
use App\Models\StockLedgerEntry;
use App\Models\SupplierBill;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BusinessIntelligenceService
{
    public function __construct(
        protected TenantContextService $tenantContext,
        protected FinancialReportService $financialReportService
    ) {}

    /**
     * 1. Executive Dashboard Key Performance Indicators (KPIs).
     * High-performance aggregated metrics respecting tenant and branch permissions.
     */
    public function getExecutiveDashboardMetrics(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        // 1. Fleet Metrics
        $vehicleQuery = Vehicle::withoutGlobalScopes()->where('company_id', $companyId);
        if ($branchId) {
            $vehicleQuery->where('branch_id', $branchId);
        }
        $totalFleet = (clone $vehicleQuery)->count();
        $activeVehicles = (clone $vehicleQuery)->whereIn('status', [Vehicle::STATUS_AVAILABLE, Vehicle::STATUS_ON_TRIP, 'active'])->count();
        $vehiclesOnTrips = (clone $vehicleQuery)->where('status', Vehicle::STATUS_ON_TRIP)->count();
        $availableVehicles = (clone $vehicleQuery)->where('status', Vehicle::STATUS_AVAILABLE)->count();
        $maintenanceVehicles = (clone $vehicleQuery)->where('status', Vehicle::STATUS_MAINTENANCE)->count();

        // 2. Driver Metrics
        $driverQuery = Driver::withoutGlobalScopes()->where('company_id', $companyId);
        if ($branchId) {
            $driverQuery->where('branch_id', $branchId);
        }
        $activeDrivers = (clone $driverQuery)->whereIn('status', [Driver::STATUS_AVAILABLE, Driver::STATUS_ON_TRIP, 'active'])->count();

        // 3. Operational Trip Metrics
        $tripQuery = Trip::withoutGlobalScopes()->where('company_id', $companyId);
        if ($branchId) {
            $tripQuery->where('branch_id', $branchId);
        }
        $tripsInProgress = (clone $tripQuery)->whereIn('trip_status', [Trip::STATUS_PLANNED, Trip::STATUS_DISPATCHED, Trip::STATUS_IN_TRANSIT, 'loading'])->count();
        $completedTrips = (clone $tripQuery)
            ->where('trip_status', Trip::STATUS_OPERATIONALLY_COMPLETED)
            ->whereBetween('trip_date', [$fromDate, $toDateTime])
            ->count();

        // 4. Consignment & Delivery Metrics
        $consignmentQuery = Consignment::withoutGlobalScopes()->where('company_id', $companyId);
        if ($branchId) {
            $consignmentQuery->where('branch_id', $branchId);
        }
        $pendingDeliveries = (clone $consignmentQuery)->whereIn('delivery_status', [
            Consignment::STATUS_BOOKED,
            Consignment::STATUS_DISPATCHED,
            Consignment::STATUS_IN_TRANSIT,
            'pending_pod'
        ])->count();

        // 5. Freight Revenue in Period
        $monthlyFreightRevenue = (float) (clone $tripQuery)
            ->whereBetween('trip_date', [$fromDate, $toDateTime])
            ->where('trip_status', '!=', Trip::STATUS_CANCELLED)
            ->sum('total_freight_amount');

        // If trip freight is 0, check customer invoices billed in period
        if ($monthlyFreightRevenue == 0) {
            $invQuery = CustomerInvoice::withoutGlobalScopes()->where('company_id', $companyId);
            if ($branchId) {
                $invQuery->where('branch_id', $branchId);
            }
            $monthlyFreightRevenue = (float) $invQuery->whereBetween('invoice_date', [$fromDate, $toDateTime])->sum('total_amount');
        }

        // 6. Direct Trip Expenses (En-route expenses + refuel costs)
        $expenseQuery = TripExpense::withoutGlobalScopes()
            ->join('trips', 'trip_expenses.trip_id', '=', 'trips.id')
            ->where('trips.company_id', $companyId)
            ->whereBetween('trip_expenses.expense_date', [$fromDate, $toDateTime])
            ->where('trip_expenses.status', TripExpense::STATUS_APPROVED);
        if ($branchId) {
            $expenseQuery->where('trips.branch_id', $branchId);
        }
        $directTripExpenses = (float) $expenseQuery->sum('trip_expenses.amount');

        $fuelQuery = FuelEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $fuelQuery->where('branch_id', $branchId);
        }
        $fuelCost = (float) $fuelQuery->sum('total_amount');

        $totalTripExpenses = $directTripExpenses + $fuelCost;

        // 7. Outstanding Receivables & Owner Payables
        $invoiceBalanceQuery = CustomerInvoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [CustomerInvoice::STATUS_UNPAID, CustomerInvoice::STATUS_PARTIAL]);
        if ($branchId) {
            $invoiceBalanceQuery->where('branch_id', $branchId);
        }
        $outstandingReceivables = (float) $invoiceBalanceQuery->sum('balance_due');

        $ownerSettlementQuery = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereIn('status', [OwnerSettlement::STATUS_CONFIRMED, OwnerSettlement::STATUS_PARTIALLY_PAID]);
        if ($branchId) {
            $ownerSettlementQuery->where('branch_id', $branchId);
        }
        $outstandingOwnerPayables = (float) $ownerSettlementQuery->sum('balance_due');

        // 8. Profit Calculations:
        // Estimated Operational Profit = Freight Revenue - Direct Expenses
        $estimatedProfit = $monthlyFreightRevenue - $totalTripExpenses;

        // Finalized Accounting Profit from General Ledger
        $pnl = $this->financialReportService->getProfitAndLoss($companyId, $fromDate, $toDate, $branchId);
        $finalizedProfit = (float) ($pnl['net_income'] ?? 0.0);

        return [
            'total_fleet' => $totalFleet,
            'active_vehicles' => $activeVehicles,
            'vehicles_on_trips' => $vehiclesOnTrips,
            'available_vehicles' => $availableVehicles,
            'vehicles_under_maintenance' => $maintenanceVehicles,
            'active_drivers' => $activeDrivers,
            'trips_in_progress' => $tripsInProgress,
            'completed_trips' => $completedTrips,
            'pending_deliveries' => $pendingDeliveries,
            'monthly_freight_revenue' => $monthlyFreightRevenue,
            'trip_expenses' => $totalTripExpenses,
            'outstanding_receivables' => $outstandingReceivables,
            'outstanding_owner_payables' => $outstandingOwnerPayables,
            'estimated_profit' => $estimatedProfit,
            'finalized_profit' => $finalizedProfit,
            'period_from' => $fromDate,
            'period_to' => $toDate,
        ];
    }

    /**
     * 2. Fleet Reports:
     * - Vehicle Register & Utilization
     * - Vehicle Maintenance & Downtime
     * - Vehicle Running Costs & Profitability
     * - Fuel Efficiency (km/L & cost/km)
     */
    public function getFleetReports(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $statusFilter = null,
        ?int $vehicleId = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $query = Vehicle::withoutGlobalScopes()
            ->with(['branch', 'owner'])
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        if ($vehicleId) {
            $query->where('id', $vehicleId);
        }

        $vehicles = $query->get();
        $records = [];

        foreach ($vehicles as $v) {
            // Trips in period
            $trips = Trip::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_id', $v->id)
                ->whereBetween('trip_date', [$fromDate, $toDateTime])
                ->get();

            $tripCount = $trips->count();
            $freightRevenue = (float) $trips->sum('total_freight_amount');

            // Fuel in period
            $fuelEntries = FuelEntry::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_id', $v->id)
                ->whereBetween('entry_date', [$fromDate, $toDateTime])
                ->get();

            $totalFuelLiters = (float) $fuelEntries->sum('quantity_liters');
            $totalFuelCost = (float) $fuelEntries->sum('total_amount');

            // Distance travelled in period
            $distanceKm = (float) $fuelEntries->sum('distance_km');
            if ($distanceKm <= 0 && $tripCount > 0) {
                // approximate from trip odometer readings if available
                $distanceKm = (float) $trips->sum(fn($t) => max(0, (int)$t->closing_odometer - (int)$t->starting_odometer));
            }

            // Maintenance Jobs in period
            $jobCards = JobCard::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_id', $v->id)
                ->whereBetween('scheduled_date', [$fromDate, $toDateTime])
                ->get();

            $maintenanceCount = $jobCards->count();
            $maintenanceCost = (float) $jobCards->sum('total_cost');
            $downtimeHours = (float) $jobCards->sum('downtime_hours');

            // En-route trip expenses
            $tripExpenses = (float) TripExpense::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_id', $v->id)
                ->whereBetween('expense_date', [$fromDate, $toDateTime])
                ->where('status', TripExpense::STATUS_APPROVED)
                ->sum('amount');

            // Total Running Cost = Fuel + Maintenance + En-route Expenses
            $totalRunningCost = $totalFuelCost + $maintenanceCost + $tripExpenses;
            $netProfit = $freightRevenue - $totalRunningCost;
            $kmPerLiter = $totalFuelLiters > 0 ? round($distanceKm / $totalFuelLiters, 2) : 0.0;
            $costPerKm = $distanceKm > 0 ? round($totalRunningCost / $distanceKm, 2) : 0.0;

            $records[] = [
                'vehicle_id' => $v->id,
                'registration_number' => $v->registration_number,
                'fleet_number' => $v->fleet_number,
                'ownership_type' => $v->ownership_type,
                'owner_name' => $v->owner?->name ?? 'Company',
                'branch_name' => $v->branch?->name ?? 'Central',
                'status' => $v->status,
                'odometer_km' => $v->current_odometer_km,
                'trip_count' => $tripCount,
                'distance_km' => $distanceKm,
                'freight_revenue' => $freightRevenue,
                'fuel_liters' => $totalFuelLiters,
                'fuel_cost' => $totalFuelCost,
                'maintenance_count' => $maintenanceCount,
                'maintenance_cost' => $maintenanceCost,
                'downtime_hours' => $downtimeHours,
                'trip_expenses' => $tripExpenses,
                'total_running_cost' => $totalRunningCost,
                'net_profit' => $netProfit,
                'fuel_efficiency_kml' => $kmPerLiter,
                'cost_per_km' => $costPerKm,
            ];
        }

        return $records;
    }

    /**
     * 3. Driver Reports:
     * - Driver Register & Trip Voyages
     * - Driver Advances, Expenses & Settlement Balances
     * - Driver Performance (Completed Trips, On-time Rate)
     */
    public function getDriverReports(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $driverId = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $query = Driver::withoutGlobalScopes()
            ->with('branch')
            ->where('company_id', $companyId);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($driverId) {
            $query->where('id', $driverId);
        }

        $drivers = $query->get();
        $records = [];

        foreach ($drivers as $d) {
            $trips = Trip::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($d) {
                    $q->where('driver_id', $d->id)
                      ->orWhereHas('resources', fn($r) => $r->where('driver_id', $d->id));
                })
                ->whereBetween('trip_date', [$fromDate, $toDateTime])
                ->get();

            $totalTrips = $trips->count();
            $completedTrips = $trips->filter(fn($t) => in_array($t->trip_status, [Trip::STATUS_OPERATIONALLY_COMPLETED, Trip::STATUS_CLOSED]))->count();

            // Driver settlements & advances
            $settlements = DriverSettlement::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('driver_id', $d->id)
                ->whereBetween('settlement_date', [$fromDate, $toDateTime])
                ->get();

            $totalAdvances = (float) $settlements->sum('advance_amount');
            $approvedExpenses = (float) $settlements->sum('approved_expenses_amount');
            $netPayableToDriver = (float) $settlements->sum('net_balance_amount');

            // Driver allowances from trip expenses
            $allowances = (float) TripExpense::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('driver_id', $d->id)
                ->whereBetween('expense_date', [$fromDate, $toDateTime])
                ->where('expense_category', 'driver_allowance')
                ->where('status', TripExpense::STATUS_APPROVED)
                ->sum('amount');

            $records[] = [
                'driver_id' => $d->id,
                'name' => $d->name,
                'employee_code' => $d->employee_code ?? "DRV-{$d->id}",
                'license_number' => $d->license_number,
                'phone' => $d->phone,
                'status' => $d->status,
                'branch_name' => $d->branch?->name ?? 'Central',
                'total_trips' => $totalTrips,
                'completed_trips' => $completedTrips,
                'total_advances' => $totalAdvances,
                'approved_expenses' => $approvedExpenses,
                'allowances_paid' => $allowances,
                'net_settlement_payable' => $netPayableToDriver,
            ];
        }

        return $records;
    }

    /**
     * 4. Customer Reports:
     * - Bookings & Freight Revenue
     * - Outstanding Balances
     * - Aging Analysis: 0-30 Days, 31-60 Days, 61-90 Days, 90+ Days
     */
    public function getCustomerAgingAndRevenueReport(
        int $companyId,
        ?int $branchId = null,
        ?string $asOfDate = null,
        ?int $customerId = null
    ): array {
        $asOfDate = $asOfDate ?? now()->toDateString();
        $asOfCarbon = Carbon::parse($asOfDate);

        $query = Customer::withoutGlobalScopes()->where('company_id', $companyId);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($customerId) {
            $query->where('id', $customerId);
        }

        $customers = $query->orderBy('name')->get();
        $records = [];

        foreach ($customers as $c) {
            // All unpaid or partial invoices up to asOfDate
            $invoices = CustomerInvoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('customer_id', $c->id)
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->whereIn('status', [CustomerInvoice::STATUS_UNPAID, CustomerInvoice::STATUS_PARTIAL])
                ->get();

            $totalOutstanding = 0.00;
            $current0To30 = 0.00;
            $days31To60 = 0.00;
            $days61To90 = 0.00;
            $days90Plus = 0.00;

            foreach ($invoices as $inv) {
                $balance = (float) $inv->balance_due;
                if ($balance <= 0) {
                    continue;
                }

                $totalOutstanding += $balance;

                $dueDate = Carbon::parse($inv->due_date);
                $daysOverdue = $asOfCarbon->diffInDays($dueDate, false);

                // Age in days relative to as-of date
                $age = abs((int) $asOfCarbon->diffInDays(Carbon::parse($inv->invoice_date), false));

                if ($age <= 30) {
                    $current0To30 += $balance;
                } elseif ($age <= 60) {
                    $days31To60 += $balance;
                } elseif ($age <= 90) {
                    $days61To90 += $balance;
                } else {
                    $days90Plus += $balance;
                }
            }

            // Total Lifetime Billed and Collected
            $totalBilled = (float) CustomerInvoice::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('customer_id', $c->id)
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->sum('total_amount');

            $totalCollected = (float) CustomerPayment::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('customer_id', $c->id)
                ->whereDate('payment_date', '<=', $asOfDate)
                ->sum('amount');

            $records[] = [
                'customer_id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'phone' => $c->phone,
                'credit_limit' => (float) $c->credit_limit,
                'total_billed' => $totalBilled,
                'total_collected' => $totalCollected,
                'total_outstanding' => $totalOutstanding,
                'current_0_to_30' => $current0To30,
                'days_31_to_60' => $days31To60,
                'days_61_to_90' => $days61To90,
                'days_90_plus' => $days90Plus,
            ];
        }

        return $records;
    }

    /**
     * 5. Trip Reports:
     * - Trip Register & Status Breakdown
     * - Route-Wise Performance (Origin -> Destination Margin)
     * - Delayed Trips & POD Compliance
     */
    public function getTripReports(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?string $statusFilter = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $query = Trip::withoutGlobalScopes()
            ->with(['branch', 'vehicle', 'driver'])
            ->where('company_id', $companyId)
            ->whereBetween('trip_date', [$fromDate, $toDateTime]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        if ($statusFilter) {
            $query->where('trip_status', $statusFilter);
        }

        $trips = $query->orderBy('trip_date', 'desc')->get();
        $records = [];

        foreach ($trips as $t) {
            $freight = (float) $t->total_freight_amount;

            // Direct fuel and expenses
            $expenses = (float) TripExpense::withoutGlobalScopes()
                ->where('trip_id', $t->id)
                ->where('status', TripExpense::STATUS_APPROVED)
                ->sum('amount');

            $fuel = (float) FuelEntry::withoutGlobalScopes()
                ->where('trip_id', $t->id)
                ->sum('total_amount');

            $totalCost = $expenses + $fuel;
            $netMargin = $freight - $totalCost;

            // POD compliance status for trip consignments
            $consignments = $t->consignments;
            $podCount = 0;
            $totalConsignments = $consignments->count();

            foreach ($consignments as $con) {
                if ($con->pod()->where('status', 'verified')->exists()) {
                    $podCount++;
                }
            }

            $podStatus = $totalConsignments > 0 && $podCount === $totalConsignments
                ? 'POD Verified'
                : ($podCount > 0 ? 'Partial POD' : 'Pending POD');

            $records[] = [
                'trip_id' => $t->id,
                'trip_number' => $t->trip_number,
                'trip_date' => $t->trip_date->format('Y-m-d'),
                'origin' => $t->origin_city ?? 'Origin',
                'destination' => $t->destination_city ?? 'Destination',
                'route' => ($t->origin_city ?? 'Origin') . ' -> ' . ($t->destination_city ?? 'Destination'),
                'vehicle' => $t->vehicle?->registration_number ?? 'Fleet',
                'driver' => $t->driver?->name ?? 'Staff',
                'status' => $t->status,
                'freight_amount' => $freight,
                'fuel_cost' => $fuel,
                'trip_expenses' => $expenses,
                'total_cost' => $totalCost,
                'net_margin' => $netMargin,
                'consignments_count' => $totalConsignments,
                'pod_status' => $podStatus,
            ];
        }

        return $records;
    }

    /**
     * Route-Wise Operational Performance Aggregation.
     */
    public function getRoutePerformanceSummary(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $query = Trip::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('trip_date', [$fromDate, $toDateTime])
            ->where('trip_status', '!=', Trip::STATUS_CANCELLED);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $routes = $query->select(
            DB::raw("COALESCE(origin_city, 'Origin') as origin"),
            DB::raw("COALESCE(destination_city, 'Destination') as destination"),
            DB::raw('COUNT(id) as total_trips'),
            DB::raw('SUM(total_freight_amount) as total_freight'),
            DB::raw('AVG(total_freight_amount) as avg_freight')
        )
        ->groupBy('origin', 'destination')
        ->orderByDesc('total_freight')
        ->get();

        return $routes->map(function ($r) {
            return [
                'route' => "{$r->origin} -> {$r->destination}",
                'origin' => $r->origin,
                'destination' => $r->destination,
                'total_trips' => (int) $r->total_trips,
                'total_freight' => (float) $r->total_freight,
                'avg_freight_per_trip' => round((float) $r->avg_freight, 2),
            ];
        })->toArray();
    }

    /**
     * 6. Vehicle Owner Reports:
     * - Owner Payables & Settlements
     * - Vehicle-wise Owner Profitability
     */
    public function getVehicleOwnerReports(
        int $companyId,
        ?int $branchId = null,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $ownerId = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $query = VehicleOwner::withoutGlobalScopes()->where('company_id', $companyId);
        if ($ownerId) {
            $query->where('id', $ownerId);
        }

        $owners = $query->orderBy('name')->get();
        $records = [];

        foreach ($owners as $ow) {
            $settlements = OwnerSettlement::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_owner_id', $ow->id)
                ->whereBetween('settlement_date', [$fromDate, $toDateTime])
                ->get();

            $totalAgreedFreight = (float) $settlements->sum('agreed_owner_payable');
            $totalDeductions = (float) $settlements->sum('approved_deductions');
            $totalAdvances = (float) $settlements->sum('advances');
            $totalNetPayable = (float) $settlements->sum('net_payable');
            $totalPaid = (float) $settlements->sum('paid_amount');
            $pendingPayable = (float) $settlements->sum('balance_due');

            // Attached vehicle count
            $attachedVehicles = Vehicle::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('vehicle_owner_id', $ow->id)
                ->count();

            $records[] = [
                'owner_id' => $ow->id,
                'name' => $ow->name,
                'code' => $ow->code ?? "OWN-{$ow->id}",
                'phone' => $ow->phone,
                'attached_vehicles' => $attachedVehicles,
                'settlements_count' => $settlements->count(),
                'agreed_freight' => $totalAgreedFreight,
                'deductions' => $totalDeductions,
                'advances' => $totalAdvances,
                'net_payable' => $totalNetPayable,
                'paid_amount' => $totalPaid,
                'pending_balance' => $pendingPayable,
            ];
        }

        return $records;
    }

    /**
     * 7. Financial Statements: Cash Flow Statement & Expense Analysis.
     */
    public function getCashFlowStatement(
        int $companyId,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $branchId = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfYear()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        // 1. Inflows from Customers (Customer Collections)
        $customerCollectionsQuery = CustomerPayment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('receipt_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $customerCollectionsQuery->where('branch_id', $branchId);
        }
        $cashInflowsCustomers = (float) $customerCollectionsQuery->sum('amount');

        // 2. Outflows: Fuel Refuel Cash / Bank Payments
        $fuelOutflowsQuery = FuelEntry::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('entry_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $fuelOutflowsQuery->where('branch_id', $branchId);
        }
        $cashOutflowsFuel = (float) $fuelOutflowsQuery->sum('total_amount');

        // 3. Outflows: Trip En-Route Expenses
        $tripExpenseOutflowQuery = TripExpense::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', TripExpense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $tripExpenseOutflowQuery->where('branch_id', $branchId);
        }
        $cashOutflowsTripExpenses = (float) $tripExpenseOutflowQuery->sum('amount');

        // 4. Outflows: Third-Party Vehicle Owner Settlements Paid
        $ownerPaymentsQuery = OwnerSettlement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('settlement_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $ownerPaymentsQuery->where('branch_id', $branchId);
        }
        $cashOutflowsOwners = (float) $ownerPaymentsQuery->sum('paid_amount');

        // 5. Outflows: Trade Parts & Supplies Payments
        $supplierPaymentQuery = \App\Models\SupplierPayment::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('payment_date', [$fromDate, $toDateTime]);
        if ($branchId) {
            $supplierPaymentQuery->where('branch_id', $branchId);
        }
        $cashOutflowsSuppliers = (float) $supplierPaymentQuery->sum('amount');

        $totalOperatingInflows = $cashInflowsCustomers;
        $totalOperatingOutflows = $cashOutflowsFuel + $cashOutflowsTripExpenses + $cashOutflowsOwners + $cashOutflowsSuppliers;
        $netCashFlow = $totalOperatingInflows - $totalOperatingOutflows;

        return [
            'period_from' => $fromDate,
            'period_to' => $toDate,
            'inflows' => [
                'customer_collections' => $cashInflowsCustomers,
                'total_inflows' => $totalOperatingInflows,
            ],
            'outflows' => [
                'diesel_fuel_payments' => $cashOutflowsFuel,
                'trip_en_route_expenses' => $cashOutflowsTripExpenses,
                'vehicle_owner_settlements' => $cashOutflowsOwners,
                'parts_supplier_disbursements' => $cashOutflowsSuppliers,
                'total_outflows' => $totalOperatingOutflows,
            ],
            'net_cash_flow' => $netCashFlow,
        ];
    }

    /**
     * 8. Inventory Reports:
     * - Stock Summary by Warehouse
     * - Low Stock Alerts
     * - Spare Parts Maintenance Consumption
     */
    public function getInventoryReports(
        int $companyId,
        ?int $warehouseId = null,
        ?string $fromDate = null,
        ?string $toDate = null
    ): array {
        $fromDate = $fromDate ?? now()->startOfMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();
        $toDateTime = Carbon::parse($toDate)->endOfDay()->toDateTimeString();

        $stockQuery = WarehouseStock::withoutGlobalScopes()
            ->with(['warehouse', 'item.primaryUom', 'item.category'])
            ->where('company_id', $companyId);

        if ($warehouseId) {
            $stockQuery->where('warehouse_id', $warehouseId);
        }

        $stocks = $stockQuery->get();
        $summary = [];
        $lowStockItems = [];

        foreach ($stocks as $s) {
            $onHand = (float) $s->quantity_on_hand;
            $available = (float) $s->available_quantity;
            $unitCost = (float) $s->average_unit_cost;
            $valuation = (float) $s->total_valuation;
            $reorderLevel = (float) ($s->item?->reorder_level ?? 0);

            $row = [
                'warehouse_name' => $s->warehouse?->name ?? 'Warehouse',
                'item_code' => $s->item?->item_code,
                'item_name' => $s->item?->name,
                'category' => $s->item?->category?->name ?? 'General',
                'uom' => $s->item?->primaryUom?->code ?? 'PCS',
                'quantity_on_hand' => $onHand,
                'available_quantity' => $available,
                'average_unit_cost' => $unitCost,
                'total_valuation' => $valuation,
                'reorder_level' => $reorderLevel,
                'is_low_stock' => $reorderLevel > 0 && $available <= $reorderLevel,
            ];

            $summary[] = $row;

            if ($row['is_low_stock']) {
                $lowStockItems[] = $row;
            }
        }

        // Spare parts issued to workshop job cards in period
        $partsConsumption = JobCardItem::withoutGlobalScopes()
            ->join('job_cards', 'job_card_items.job_card_id', '=', 'job_cards.id')
            ->leftJoin('vehicles', 'job_cards.vehicle_id', '=', 'vehicles.id')
            ->where('job_card_items.company_id', $companyId)
            ->where('job_card_items.is_inventory_item', true)
            ->whereBetween('job_cards.scheduled_date', [$fromDate, $toDateTime])
            ->select(
                'job_card_items.part_name',
                'job_card_items.part_number',
                'vehicles.registration_number as vehicle_registration',
                'job_cards.job_card_number',
                'job_cards.scheduled_date',
                'job_card_items.quantity',
                'job_card_items.unit_cost',
                'job_card_items.total_cost'
            )
            ->get()
            ->toArray();

        return [
            'summary' => $summary,
            'low_stock_items' => $lowStockItems,
            'parts_consumption' => $partsConsumption,
            'total_stock_valuation' => array_sum(array_column($summary, 'total_valuation')),
        ];
    }
}

