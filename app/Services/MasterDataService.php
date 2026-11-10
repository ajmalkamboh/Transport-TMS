<?php

namespace App\Services;

use App\Models\MasterData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterDataService
{
    /**
     * Get accessible master data items for a category and tenant company.
     * Merges company custom lookups and system defaults.
     */
    public function getItemsByCategory(string $category, ?int $companyId = null): Collection
    {
        return MasterData::query()
            ->category($category)
            ->active()
            ->forCompany($companyId)
            ->get();
    }

    /**
     * Seed default system-wide transport master data items.
     */
    public function seedSystemDefaultLookups(): void
    {
        $defaults = $this->getDefaultMasterDataDefinitions();

        foreach ($defaults as $item) {
            MasterData::withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => null,
                    'category' => $item['category'],
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? null,
                    'meta' => $item['meta'] ?? null,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $item['sort_order'] ?? 0,
                ]
            );
        }
    }

    /**
     * Create a new tenant-specific master data item.
     */
    public function createTenantCustomItem(
        int $companyId,
        string $category,
        string $code,
        string $name,
        ?string $description = null,
        ?array $meta = null
    ): MasterData {
        return MasterData::create([
            'company_id' => $companyId,
            'category' => $category,
            'code' => strtoupper($code),
            'name' => $name,
            'description' => $description,
            'meta' => $meta,
            'is_system' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Get master definitions catalogue for all 12 categories.
     */
    public function getDefaultMasterDataDefinitions(): array
    {
        return [
            // 1. Vehicle Categories
            ['category' => MasterData::CATEGORY_VEHICLE_CATEGORY, 'code' => 'HCV', 'name' => 'Heavy Commercial Vehicle (HCV)', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_VEHICLE_CATEGORY, 'code' => 'MCV', 'name' => 'Medium Commercial Vehicle (MCV)', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_VEHICLE_CATEGORY, 'code' => 'LCV', 'name' => 'Light Commercial Vehicle (LCV)', 'sort_order' => 3],

            // 2. Vehicle Types
            ['category' => MasterData::CATEGORY_VEHICLE_TYPE, 'code' => 'TRAILER_22W', 'name' => '22-Wheeler Articulated Trailer (50 Ton)', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_VEHICLE_TYPE, 'code' => 'FLATBED_10W', 'name' => '10-Wheeler Rigid Flatbed (30 Ton)', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_VEHICLE_TYPE, 'code' => 'BOX_6W', 'name' => '6-Wheeler Enclosed Box Truck (15 Ton)', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_VEHICLE_TYPE, 'code' => 'CONTAINER_SKELETAL', 'name' => '40ft Skeletal Container Chassis', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_VEHICLE_TYPE, 'code' => 'REEFER_TRUCK', 'name' => 'Insulated Reefer Temperature Controlled Truck', 'sort_order' => 5],

            // 3. Fuel Types
            ['category' => MasterData::CATEGORY_FUEL_TYPE, 'code' => 'DIESEL', 'name' => 'High Speed Diesel (HSD)', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_FUEL_TYPE, 'code' => 'PETROL', 'name' => 'Super Unleaded Petrol', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_FUEL_TYPE, 'code' => 'CNG', 'name' => 'Compressed Natural Gas (CNG)', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_FUEL_TYPE, 'code' => 'ELECTRIC', 'name' => 'Commercial EV Battery Charging', 'sort_order' => 4],

            // 4. Trip Expense Categories
            ['category' => MasterData::CATEGORY_EXPENSE_CATEGORY, 'code' => 'TOLL_TAX', 'name' => 'Highway & Motorway Toll Charges', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_EXPENSE_CATEGORY, 'code' => 'POLICE_CHALLAN', 'name' => 'Traffic / Highway Authority Challan', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_EXPENSE_CATEGORY, 'code' => 'LABOR_UNLOAD', 'name' => 'Loading & Offloading Labor (Palledari)', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_EXPENSE_CATEGORY, 'code' => 'WEIGHBRIDGE', 'name' => 'Weight Bridge / Kanta Slip Charges', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_EXPENSE_CATEGORY, 'code' => 'DRIVER_DAILY', 'name' => 'Driver Daily Meal Allowance (Batta)', 'sort_order' => 5],

            // 5. Maintenance Categories
            ['category' => MasterData::CATEGORY_MAINTENANCE_CATEGORY, 'code' => 'OIL_LUBRICANTS', 'name' => 'Engine & Transmission Oil Service', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_MAINTENANCE_CATEGORY, 'code' => 'TIRES_TUBES', 'name' => 'Tire Replacement & Wheel Alignment', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_MAINTENANCE_CATEGORY, 'code' => 'BRAKES_CLUTCH', 'name' => 'Brake Shoes & Clutch Overhaul', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_MAINTENANCE_CATEGORY, 'code' => 'ELECTRICAL', 'name' => 'Battery & Wiring Repair', 'sort_order' => 4],

            // 6. Freight Units
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'MT', 'name' => 'Metric Ton (1,000 Kg)', 'meta' => ['symbol' => 'MT'], 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'KG', 'name' => 'Kilogram', 'meta' => ['symbol' => 'KG'], 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'BAG', 'name' => 'Standard Bag (50 Kg)', 'meta' => ['symbol' => 'Bag'], 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'BALE', 'name' => 'Cotton / Textile Bale', 'meta' => ['symbol' => 'Bale'], 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'CARTON', 'name' => 'Corrugated Box Carton', 'meta' => ['symbol' => 'Ctn'], 'sort_order' => 5],
            ['category' => MasterData::CATEGORY_FREIGHT_UNIT, 'code' => 'DRUM', 'name' => 'Liquid Chemical Drum (200L)', 'meta' => ['symbol' => 'Drum'], 'sort_order' => 6],

            // 7. Cargo Categories
            ['category' => MasterData::CATEGORY_CARGO_CATEGORY, 'code' => 'FMCG', 'name' => 'FMCG & Packaged Food Products', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_CARGO_CATEGORY, 'code' => 'TEXTILES', 'name' => 'Raw Cotton, Yarn & Garments', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_CARGO_CATEGORY, 'code' => 'CEMENT_STEEL', 'name' => 'Cement, Steel Rods & Construction Aggregates', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_CARGO_CATEGORY, 'code' => 'CHEMICALS', 'name' => 'Agricultural Fertilizer & Industrial Chemicals', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_CARGO_CATEGORY, 'code' => 'AGRICULTURE', 'name' => 'Wheat, Rice & Seasonal Crops', 'sort_order' => 5],

            // 8. Payment Methods
            ['category' => MasterData::CATEGORY_PAYMENT_METHOD, 'code' => 'BANK_TRANSFER', 'name' => 'Bank Wire Transfer / Raast / IBFT', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_PAYMENT_METHOD, 'code' => 'CHEQUE', 'name' => 'Bank Crossing Cheque', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_PAYMENT_METHOD, 'code' => 'CASH', 'name' => 'Spot Cash on Counter', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_PAYMENT_METHOD, 'code' => 'PAY_ORDER', 'name' => 'Bank Demand Draft / Pay Order', 'sort_order' => 4],

            // 9. Trip Statuses
            ['category' => MasterData::CATEGORY_TRIP_STATUS, 'code' => 'PLANNED', 'name' => 'Planned & Manifest Created', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_TRIP_STATUS, 'code' => 'DISPATCHED', 'name' => 'Dispatched from Terminal', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_TRIP_STATUS, 'code' => 'IN_TRANSIT', 'name' => 'En-Route on Highway', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_TRIP_STATUS, 'code' => 'ARRIVED', 'name' => 'Arrived at Destination Depot', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_TRIP_STATUS, 'code' => 'COMPLETED', 'name' => 'Trip Manifest Completed & Reconciled', 'sort_order' => 5],

            // 10. Delivery Statuses
            ['category' => MasterData::CATEGORY_DELIVERY_STATUS, 'code' => 'PENDING', 'name' => 'Awaiting Delivery Dispatch', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_DELIVERY_STATUS, 'code' => 'OUT_FOR_DELIVERY', 'name' => 'Out for Consignee Delivery', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_DELIVERY_STATUS, 'code' => 'DELIVERED_CLEAN', 'name' => 'Delivered Clean with Signed POD', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_DELIVERY_STATUS, 'code' => 'DELIVERED_SHORTAGE', 'name' => 'Delivered with Shortage', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_DELIVERY_STATUS, 'code' => 'DELIVERED_DAMAGE', 'name' => 'Delivered with Cargo Damage', 'sort_order' => 5],

            // 11. Route Locations
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'LHR', 'name' => 'Lahore Logistics Central Hub', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'KHI_PORT', 'name' => 'Karachi Port Trust (KPT)', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'KHI_PQ', 'name' => 'Port Muhammad Bin Qasim (PQ)', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'ISB', 'name' => 'Islamabad / Rawalpindi Terminal', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'FSD', 'name' => 'Faisalabad Textile Hub', 'sort_order' => 5],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'MUX', 'name' => 'Multan Southern Depot', 'sort_order' => 6],
            ['category' => MasterData::CATEGORY_ROUTE_LOCATION, 'code' => 'PEW', 'name' => 'Peshawar Khyber Terminal', 'sort_order' => 7],

            // 12. Document Types
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'QUOTATION', 'name' => 'Freight Quotation (QT)', 'sort_order' => 1],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'BOOKING', 'name' => 'Cargo Booking (BK)', 'sort_order' => 2],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'CONSIGNMENT', 'name' => 'Lorry Receipt / Bilty (LR)', 'sort_order' => 3],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'TRIP', 'name' => 'Trip Manifest (TR)', 'sort_order' => 4],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'INVOICE', 'name' => 'Customer Freight Invoice (INV)', 'sort_order' => 5],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'RECEIPT', 'name' => 'Payment Receipt (RCP)', 'sort_order' => 6],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'PAYMENT', 'name' => 'Payment Voucher (PAY)', 'sort_order' => 7],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'EXPENSE_VOUCHER', 'name' => 'Trip Expense Voucher (EXP)', 'sort_order' => 8],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'JOURNAL_VOUCHER', 'name' => 'General Journal Voucher (JV)', 'sort_order' => 9],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'OWNER_SETTLEMENT', 'name' => 'Owner Settlement Voucher (STL)', 'sort_order' => 10],
            ['category' => MasterData::CATEGORY_DOCUMENT_TYPE, 'code' => 'PURCHASE_ORDER', 'name' => 'Spares & Fuel Purchase Order (PO)', 'sort_order' => 11],
        ];
    }
}
