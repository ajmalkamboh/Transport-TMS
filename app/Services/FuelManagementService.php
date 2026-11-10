<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\FuelCard;
use App\Models\FuelEntry;
use App\Models\FuelStation;
use App\Models\FuelSupplier;
use App\Models\MaintenanceAlert;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class FuelManagementService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog
    ) {}

    public function createSupplier(Company $company, array $data): FuelSupplier
    {
        return DB::transaction(function () use ($company, $data) {
            $supplier = FuelSupplier::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'code' => $data['code'] ?? null,
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'ntn_number' => $data['ntn_number'] ?? null,
                'address' => $data['address'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? 0.00,
                'balance' => $data['balance'] ?? 0.00,
                'payment_terms_days' => $data['payment_terms_days'] ?? 30,
                'status' => $data['status'] ?? FuelSupplier::STATUS_ACTIVE,
            ]);

            $this->auditLog->log('fuel_supplier_created', $supplier, null, $supplier->toArray(), 'New fuel supplier registered.');

            return $supplier;
        });
    }

    public function createStation(Company $company, array $data): FuelStation
    {
        return FuelStation::create([
            'company_id' => $company->id,
            'fuel_supplier_id' => $data['fuel_supplier_id'] ?? null,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'city' => $data['city'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => $data['status'] ?? FuelStation::STATUS_ACTIVE,
        ]);
    }

    public function createFuelCard(Company $company, array $data): FuelCard
    {
        return FuelCard::create([
            'company_id' => $company->id,
            'fuel_supplier_id' => $data['fuel_supplier_id'] ?? null,
            'card_number' => $data['card_number'],
            'assigned_vehicle_id' => $data['assigned_vehicle_id'] ?? null,
            'assigned_driver_id' => $data['assigned_driver_id'] ?? null,
            'monthly_limit' => $data['monthly_limit'] ?? 0.00,
            'current_balance' => $data['current_balance'] ?? ($data['monthly_limit'] ?? 0.00),
            'expiry_date' => $data['expiry_date'] ?? null,
            'status' => $data['status'] ?? FuelCard::STATUS_ACTIVE,
        ]);
    }

    /**
     * Record refuel entry with odometer validation, distance calculation,
     * KM/L fuel efficiency metrics, and anomaly detection.
     *
     * @throws \InvalidArgumentException
     */
    public function recordFuelEntry(Company $company, array $data, ?int $userId = null): FuelEntry
    {
        return DB::transaction(function () use ($company, $data, $userId) {
            $vehicle = Vehicle::where('company_id', $company->id)->findOrFail($data['vehicle_id']);
            $currentOdo = (int) $data['odometer_km'];
            $prevOdo = (int) ($data['previous_odometer_km'] ?? $vehicle->current_odometer);

            $isSuspicious = false;
            $anomalyReason = null;
            $distance = 0;

            // 1. Odometer Validation & Anomaly Detection
            if ($currentOdo < $prevOdo) {
                $isSuspicious = true;
                $anomalyReason = sprintf('Decreasing odometer reading: previous [%d KM] vs entered [%d KM].', $prevOdo, $currentOdo);
            } else {
                $distance = $currentOdo - $prevOdo;
                if ($distance > 3000) {
                    $isSuspicious = true;
                    $anomalyReason = sprintf('Unusually large distance jump [%d KM] recorded in single refuel.', $distance);
                }

                // Update vehicle current odometer if higher
                if ($currentOdo > $vehicle->current_odometer) {
                    $vehicle->current_odometer = $currentOdo;
                    $vehicle->save();
                }
            }

            // 2. Pricing and Total Amount
            $qty = (float) $data['quantity_liters'];
            $rate = (float) $data['rate_per_liter'];
            $totalAmount = $data['total_amount'] ?? round($qty * $rate, 2);

            // 3. Fuel Efficiency & Cost per KM Calculations
            $economyKmPerL = null;
            $costPerKm = null;
            if ($distance > 0 && $qty > 0) {
                $economyKmPerL = round($distance / $qty, 2);
                $costPerKm = round($totalAmount / $distance, 2);

                // Benchmark validation for commercial heavy goods vehicles (normal range 1.8 to 6.0 KM/L)
                if ($economyKmPerL < 1.2 || $economyKmPerL > 7.5) {
                    $isSuspicious = true;
                    $anomalyReason = ($anomalyReason ? $anomalyReason . ' | ' : '') .
                        sprintf('Abnormal fuel economy: [%.2f KM/L] deviates significantly from fleet norms.', $economyKmPerL);
                }
            }

            // 4. Generate Atomic Sequential Number
            $entryNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_FUEL_ENTRY,
                null,
                5,
                $company->id,
                $data['branch_id'] ?? null
            );

            // 5. Create Fuel Entry Record
            $entry = FuelEntry::create([
                'company_id' => $company->id,
                'branch_id' => $data['branch_id'] ?? $vehicle->branch_id,
                'entry_number' => $entryNumber,
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'fuel_supplier_id' => $data['fuel_supplier_id'] ?? null,
                'fuel_station_id' => $data['fuel_station_id'] ?? null,
                'fuel_card_id' => $data['fuel_card_id'] ?? null,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $data['driver_id'] ?? null,
                'trip_id' => $data['trip_id'] ?? null,
                'fuel_type' => $data['fuel_type'] ?? FuelEntry::FUEL_DIESEL,
                'quantity_liters' => $qty,
                'rate_per_liter' => $rate,
                'total_amount' => $totalAmount,
                'payment_method' => $data['payment_method'] ?? 'fuel_card',
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'odometer_km' => $currentOdo,
                'previous_odometer_km' => $prevOdo,
                'distance_km' => $distance,
                'fuel_consumed_liters' => $data['fuel_consumed_liters'] ?? $qty,
                'fuel_economy_km_per_liter' => $economyKmPerL,
                'cost_per_km' => $costPerKm,
                'is_full_tank' => $data['is_full_tank'] ?? true,
                'is_suspicious' => $isSuspicious,
                'anomaly_reason' => $anomalyReason,
                'receipt_photo_path' => $data['receipt_photo_path'] ?? null,
                'invoice_reference' => $data['invoice_reference'] ?? null,
                'status' => $data['status'] ?? FuelEntry::STATUS_VERIFIED,
                'created_by' => $userId ?? auth()->id() ?? 1,
            ]);

            // 6. If Fuel Card used, update card balance
            if (!empty($data['fuel_card_id'])) {
                $card = FuelCard::find($data['fuel_card_id']);
                if ($card) {
                    $card->current_balance = max(0, (float) $card->current_balance - $totalAmount);
                    $card->save();
                }
            }

            // 7. If suspicious or anomaly detected, create Fleet Maintenance Alert
            if ($isSuspicious) {
                MaintenanceAlert::create([
                    'company_id' => $company->id,
                    'vehicle_id' => $vehicle->id,
                    'alert_type' => MaintenanceAlert::TYPE_FUEL_ANOMALY,
                    'title' => 'Fuel & Mileage Anomaly Detected',
                    'description' => "Refuel [{$entryNumber}] flagged: {$anomalyReason}",
                    'severity' => MaintenanceAlert::SEVERITY_WARNING,
                    'due_odometer_km' => $currentOdo,
                ]);
            }

            $this->auditLog->log('fuel_entry_recorded', $entry, null, [
                'liters' => $qty,
                'total' => $totalAmount,
                'km_per_liter' => $economyKmPerL,
                'is_suspicious' => $isSuspicious,
            ], 'Fuel issue and odometer logged.');

            return $entry;
        });
    }

    /**
     * Compute comprehensive fleet metrics for a specific vehicle.
     */
    public function getVehicleFuelMetrics(Vehicle $vehicle): array
    {
        $entries = FuelEntry::where('vehicle_id', $vehicle->id)->get();

        $totalLiters = (float) $entries->sum('quantity_liters');
        $totalCost = (float) $entries->sum('total_amount');
        $totalDistance = (int) $entries->sum('distance_km');

        $avgKmPerLiter = ($totalDistance > 0 && $totalLiters > 0)
            ? round($totalDistance / $totalLiters, 2)
            : 0.00;

        $avgCostPerKm = ($totalDistance > 0 && $totalCost > 0)
            ? round($totalCost / $totalDistance, 2)
            : 0.00;

        return [
            'total_entries_count' => $entries->count(),
            'total_liters' => $totalLiters,
            'total_cost' => $totalCost,
            'total_distance_km' => $totalDistance,
            'avg_km_per_liter' => $avgKmPerLiter,
            'avg_cost_per_km' => $avgCostPerKm,
            'suspicious_entries_count' => $entries->where('is_suspicious', true)->count(),
        ];
    }
}
