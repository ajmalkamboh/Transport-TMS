<?php

namespace App\Http\Controllers;

use App\Services\BusinessIntelligenceService;
use App\Services\FinancialReportService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        protected BusinessIntelligenceService $biService,
        protected FinancialReportService $financialReportService,
        protected TenantContextService $tenantContext,
        protected TenantAuthorizationService $tenantAuth
    ) {}

    /**
     * Stream CSV / Excel export for any BI report.
     * Memory-efficient stream response using chunked/aggregated data.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $companyId = $this->tenantContext->getCompanyId() ?? auth()->user()->company_id;
        $tab = $request->get('tab', 'fleet');
        $branchId = $request->get('branch_id') ? (int) $request->get('branch_id') : null;
        $fromDate = $request->get('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        $filename = "tms_{$tab}_report_" . date('Ymd_His') . ".csv";

        return response()->stream(function () use ($companyId, $tab, $branchId, $fromDate, $toDate) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($tab === 'fleet') {
                fputcsv($handle, [
                    'Registration Number', 'Fleet #', 'Ownership', 'Owner', 'Branch',
                    'Status', 'Trips Count', 'Distance (KM)', 'Freight Revenue (PKR)',
                    'Fuel Cost (PKR)', 'Maintenance Cost (PKR)', 'Downtime (Hrs)',
                    'Total Running Cost (PKR)', 'Net Profit (PKR)', 'Fuel Efficiency (KM/L)', 'Cost/KM (PKR)'
                ]);

                $records = $this->biService->getFleetReports($companyId, $branchId, $fromDate, $toDate);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['registration_number'], $r['fleet_number'], $r['ownership_type'], $r['owner_name'], $r['branch_name'],
                        $r['status'], $r['trip_count'], $r['distance_km'], $r['freight_revenue'],
                        $r['fuel_cost'], $r['maintenance_cost'], $r['downtime_hours'],
                        $r['total_running_cost'], $r['net_profit'], $r['fuel_efficiency_kml'], $r['cost_per_km']
                    ]);
                }
            } elseif ($tab === 'customer') {
                fputcsv($handle, [
                    'Customer Code', 'Customer Name', 'Phone', 'Credit Limit (PKR)',
                    'Total Billed (PKR)', 'Total Collected (PKR)', 'Total Outstanding (PKR)',
                    'Current 0-30 Days', '31-60 Days', '61-90 Days', '90+ Days Overdue'
                ]);

                $records = $this->biService->getCustomerAgingAndRevenueReport($companyId, $branchId, $toDate);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['code'], $r['name'], $r['phone'], $r['credit_limit'],
                        $r['total_billed'], $r['total_collected'], $r['total_outstanding'],
                        $r['current_0_to_30'], $r['days_31_to_60'], $r['days_61_to_90'], $r['days_90_plus']
                    ]);
                }
            } elseif ($tab === 'driver') {
                fputcsv($handle, [
                    'Code', 'Driver Name', 'License #', 'Phone', 'Branch',
                    'Total Trips', 'Completed Trips', 'Advances (PKR)',
                    'Approved Expenses (PKR)', 'Allowances Paid (PKR)', 'Net Settlement Balance (PKR)'
                ]);

                $records = $this->biService->getDriverReports($companyId, $branchId, $fromDate, $toDate);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['employee_code'], $r['name'], $r['license_number'], $r['phone'], $r['branch_name'],
                        $r['total_trips'], $r['completed_trips'], $r['total_advances'],
                        $r['approved_expenses'], $r['allowances_paid'], $r['net_settlement_payable']
                    ]);
                }
            } elseif ($tab === 'trip') {
                fputcsv($handle, [
                    'Trip #', 'Date', 'Route', 'Vehicle', 'Driver',
                    'Status', 'Freight Amount (PKR)', 'Fuel Cost (PKR)',
                    'Trip Expenses (PKR)', 'Total Trip Cost (PKR)', 'Net Margin (PKR)', 'POD Status'
                ]);

                $records = $this->biService->getTripReports($companyId, $branchId, $fromDate, $toDate);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['trip_number'], $r['trip_date'], $r['route'], $r['vehicle'], $r['driver'],
                        $r['status'], $r['freight_amount'], $r['fuel_cost'],
                        $r['trip_expenses'], $r['total_cost'], $r['net_margin'], $r['pod_status']
                    ]);
                }
            } elseif ($tab === 'owner') {
                fputcsv($handle, [
                    'Owner Code', 'Owner Name', 'Phone', 'Attached Vehicles',
                    'Settlements Count', 'Agreed Freight (PKR)', 'Deductions (PKR)',
                    'Advances (PKR)', 'Net Payable (PKR)', 'Paid Amount (PKR)', 'Pending Balance (PKR)'
                ]);

                $records = $this->biService->getVehicleOwnerReports($companyId, $branchId, $fromDate, $toDate);
                foreach ($records as $r) {
                    fputcsv($handle, [
                        $r['code'], $r['name'], $r['phone'], $r['attached_vehicles'],
                        $r['settlements_count'], $r['agreed_freight'], $r['deductions'],
                        $r['advances'], $r['net_payable'], $r['paid_amount'], $r['pending_balance']
                    ]);
                }
            } elseif ($tab === 'inventory') {
                fputcsv($handle, [
                    'Warehouse', 'Item Code', 'Item Name', 'Category', 'UOM',
                    'Quantity On Hand', 'Available Qty', 'Average Unit Cost (PKR)',
                    'Total Valuation (PKR)', 'Reorder Level', 'Low Stock Alert'
                ]);

                $report = $this->biService->getInventoryReports($companyId, null, $fromDate, $toDate);
                foreach ($report['summary'] as $r) {
                    fputcsv($handle, [
                        $r['warehouse_name'], $r['item_code'], $r['item_name'], $r['category'], $r['uom'],
                        $r['quantity_on_hand'], $r['available_quantity'], $r['average_unit_cost'],
                        $r['total_valuation'], $r['reorder_level'], $r['is_low_stock'] ? 'YES' : 'NO'
                    ]);
                }
            } else {
                fputcsv($handle, ['Report Type', 'From Date', 'To Date', 'Generated At']);
                fputcsv($handle, [$tab, $fromDate, $toDate, now()->toDateTimeString()]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Render printable report view for printing or PDF export.
     */
    public function printReport(Request $request)
    {
        $company = $this->tenantContext->getCompany() ?? auth()->user()->company;
        $companyId = $company->id;
        $tab = $request->get('tab', 'executive');
        $branchId = $request->get('branch_id') ? (int) $request->get('branch_id') : null;
        $fromDate = $request->get('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        $data = [];
        $title = 'Business Intelligence & Operations Report';

        if ($tab === 'executive') {
            $title = 'Executive Operational & Financial Summary';
            $data = $this->biService->getExecutiveDashboardMetrics($companyId, $branchId, $fromDate, $toDate);
        } elseif ($tab === 'fleet') {
            $title = 'Fleet Utilization, Maintenance & Profitability Report';
            $data = $this->biService->getFleetReports($companyId, $branchId, $fromDate, $toDate);
        } elseif ($tab === 'customer') {
            $title = 'Customer Revenue & 0-90+ Days Aging Report';
            $data = $this->biService->getCustomerAgingAndRevenueReport($companyId, $branchId, $toDate);
        } elseif ($tab === 'driver') {
            $title = 'Driver Operations, Advances & Settlement Report';
            $data = $this->biService->getDriverReports($companyId, $branchId, $fromDate, $toDate);
        } elseif ($tab === 'trip') {
            $title = 'Trip Operations & Route Performance Report';
            $data = [
                'trips' => $this->biService->getTripReports($companyId, $branchId, $fromDate, $toDate),
                'routes' => $this->biService->getRoutePerformanceSummary($companyId, $branchId, $fromDate, $toDate),
            ];
        } elseif ($tab === 'owner') {
            $title = 'Attached Vehicle Owner Payables & Settlement Report';
            $data = $this->biService->getVehicleOwnerReports($companyId, $branchId, $fromDate, $toDate);
        } elseif ($tab === 'inventory') {
            $title = 'Inventory Valuation & Spare Parts Movement Report';
            $data = $this->biService->getInventoryReports($companyId, null, $fromDate, $toDate);
        }

        return view('print.business-report', [
            'company' => $company,
            'tab' => $tab,
            'title' => $title,
            'data' => $data,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }
}

