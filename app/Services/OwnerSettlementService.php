<?php

namespace App\Services;

use App\Models\DocumentSequence;
use App\Models\FuelEntry;
use App\Models\JobCard;
use App\Models\OwnerContract;
use App\Models\OwnerLedger;
use App\Models\OwnerPayment;
use App\Models\OwnerSettlement;
use App\Models\OwnerSettlementDeduction;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OwnerSettlementService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService,
        protected OwnerLedgerService $ledgerService,
        protected AuditLogService $auditLog
    ) {}

    /**
     * Calculate owner payable using the active owner contract or negotiated terms.
     * Retains contract version and calculation details.
     */
    public function calculateOwnerPayable(
        Trip $trip,
        ?OwnerContract $contract = null,
        array $customTerms = []
    ): array {
        $vehicle = $trip->primaryVehicle();

        if (!$vehicle) {
            // Check any vehicle resource assigned
            $vehicleResource = $trip->resources()->where('resource_type', 'vehicle')->first();
            $vehicle = $vehicleResource?->vehicle;
        }

        if (!$vehicle || !$vehicle->vehicle_owner_id) {
            throw new \InvalidArgumentException('Trip does not have an attached third-party vehicle with a registered owner.');
        }

        $owner = $vehicle->owner;
        $resolvedContract = $contract ?? $vehicle->contract ?? $owner->activeContract;

        // Calculate customer freight reference across consignments on this trip
        $customerFreightTotal = (float) $trip->consignments()->sum('total_charges');
        if ($customerFreightTotal <= 0) {
            $customerFreightTotal = (float) $trip->consignments()->sum('freight_charge');
        }
        if ($customerFreightTotal <= 0 && $trip->tripConsignments()->exists()) {
            // Fallback to weight-based or consignment rates if available
            $customerFreightTotal = (float) $trip->tripConsignments()->sum('weight_kg') * 5.0; // standard benchmark fallback
        }

        $distanceKm = (int) $trip->total_distance_km;
        if ($distanceKm <= 0 && $trip->arrival_odometer_km > $trip->departure_odometer_km) {
            $distanceKm = $trip->arrival_odometer_km - $trip->departure_odometer_km;
        }

        // Determine payment model and compute gross payable
        $paymentModel = $customTerms['payment_model'] ?? ($resolvedContract ? $resolvedContract->payment_model : OwnerSettlement::MODEL_FIXED_PER_TRIP);
        $agreedPayable = 0.00;
        $formulaDetails = '';

        if (!empty($customTerms['negotiated_rate'])) {
            $paymentModel = OwnerSettlement::MODEL_NEGOTIATED;
            $agreedPayable = (float) $customTerms['negotiated_rate'];
            $formulaDetails = "Special Negotiated Rate agreed for Trip: PKR " . number_format($agreedPayable, 2);
        } else {
            switch ($paymentModel) {
                case OwnerContract::MODEL_PERCENTAGE_OF_FREIGHT:
                    $freightPercent = $resolvedContract ? (float) $resolvedContract->freight_percentage : 80.00;
                    if ($freightPercent <= 0 && $resolvedContract && $resolvedContract->commission_percentage > 0) {
                        $freightPercent = 100.00 - (float) $resolvedContract->commission_percentage;
                    }
                    $agreedPayable = round($customerFreightTotal * ($freightPercent / 100.0), 2);
                    $formulaDetails = "Freight {$customerFreightTotal} * {$freightPercent}%";
                    break;

                case OwnerContract::MODEL_PER_KM:
                    $ratePerKm = $resolvedContract ? (float) $resolvedContract->rate_amount : 120.00;
                    $agreedPayable = round($distanceKm * $ratePerKm, 2);
                    $formulaDetails = "{$distanceKm} KM @ PKR " . number_format($ratePerKm, 2) . "/KM";
                    break;

                case OwnerContract::MODEL_MONTHLY_RENTAL:
                    $agreedPayable = $resolvedContract ? (float) $resolvedContract->rate_amount : 250000.00;
                    $formulaDetails = "Monthly Dedicated Lease installment: PKR " . number_format($agreedPayable, 2);
                    break;

                case OwnerContract::MODEL_ROUTE_SPECIFIC:
                    $agreedPayable = $resolvedContract ? (float) $resolvedContract->rate_amount : 45000.00;
                    $formulaDetails = "Route Agreement ({$trip->origin_city} -> {$trip->destination_city}): PKR " . number_format($agreedPayable, 2);
                    break;

                case OwnerContract::MODEL_FIXED_PER_TRIP:
                default:
                    $agreedPayable = $resolvedContract ? (float) $resolvedContract->rate_amount : 35000.00;
                    $formulaDetails = "Fixed Rate Per Trip: PKR " . number_format($agreedPayable, 2);
                    break;
            }
        }

        // Snapshot of contract terms to preserve version immutability
        $contractSnapshot = [
            'contract_id' => $resolvedContract?->id,
            'contract_number' => $resolvedContract?->contract_number ?? 'NONE',
            'payment_model' => $paymentModel,
            'rate_amount' => $resolvedContract ? (float) $resolvedContract->rate_amount : null,
            'freight_percentage' => $resolvedContract ? (float) $resolvedContract->freight_percentage : null,
            'commission_percentage' => $resolvedContract ? (float) $resolvedContract->commission_percentage : null,
            'deduct_fuel' => $resolvedContract ? (bool) $resolvedContract->deduct_fuel : true,
            'deduct_toll_taxes' => $resolvedContract ? (bool) $resolvedContract->deduct_toll_taxes : false,
            'deduct_advances' => $resolvedContract ? (bool) $resolvedContract->deduct_advances : true,
            'version_captured_at' => now()->toIso8601String(),
        ];

        // Detailed math breakdown
        $calculationDetails = [
            'payment_model' => $paymentModel,
            'customer_freight_total' => $customerFreightTotal,
            'distance_km' => $distanceKm,
            'gross_payable' => $agreedPayable,
            'formula' => $formulaDetails,
            'rate_amount_used' => $resolvedContract?->rate_amount,
            'freight_percentage_used' => $resolvedContract?->freight_percentage,
        ];

        return [
            'vehicle' => $vehicle,
            'owner' => $owner,
            'contract' => $resolvedContract,
            'payment_model' => $paymentModel,
            'customer_freight_total' => $customerFreightTotal,
            'agreed_owner_payable' => $agreedPayable,
            'contract_snapshot' => $contractSnapshot,
            'calculation_details' => $calculationDetails,
        ];
    }

    /**
     * Detect eligible pending deductions for an attached vehicle on a trip,
     * ensuring no item is deducted twice.
     */
    public function detectPendingDeductions(Trip $trip): array
    {
        $vehicle = $trip->primaryVehicle() ?? $trip->resources()->where('resource_type', 'vehicle')->first()?->vehicle;
        if (!$vehicle || !$vehicle->vehicle_owner_id) {
            return [];
        }

        $deductions = [];

        // 1. Fuel entries attached to this trip or vehicle that were paid by company
        $fuelEntries = FuelEntry::where('company_id', $trip->company_id)
            ->where(function ($q) use ($trip, $vehicle) {
                $q->where('trip_id', $trip->id)
                    ->orWhere(function ($sq) use ($trip, $vehicle) {
                        $sq->where('vehicle_id', $vehicle->id)
                            ->whereBetween('entry_date', [$trip->actual_departure ?? $trip->trip_date, $trip->actual_arrival ?? now()]);
                    });
            })
            ->get();

        foreach ($fuelEntries as $fuel) {
            // Check if already deducted in any non-reversed settlement
            $alreadyDeducted = OwnerSettlementDeduction::where('company_id', $trip->company_id)
                ->where('reference_type', FuelEntry::class)
                ->where('reference_id', $fuel->id)
                ->whereHas('settlement', function ($sq) {
                    $sq->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED]);
                })
                ->exists();

            if (!$alreadyDeducted) {
                $deductions[] = [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_FUEL,
                    'amount' => (float) $fuel->total_amount,
                    'reference_type' => FuelEntry::class,
                    'reference_id' => $fuel->id,
                    'description' => "Fuel supplied by company (Slip: {$fuel->entry_number}, {$fuel->quantity_liters}L @ PKR {$fuel->rate_per_liter})",
                ];
            }
        }

        // 2. Trip Expenses (Advances, emergency repairs)
        $expenses = TripExpense::where('company_id', $trip->company_id)
            ->where('trip_id', $trip->id)
            ->whereIn('status', [TripExpense::STATUS_APPROVED, TripExpense::STATUS_POSTED])
            ->get();

        foreach ($expenses as $expense) {
            $alreadyDeducted = OwnerSettlementDeduction::where('company_id', $trip->company_id)
                ->where('reference_type', TripExpense::class)
                ->where('reference_id', $expense->id)
                ->whereHas('settlement', function ($sq) {
                    $sq->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED]);
                })
                ->exists();

            if (!$alreadyDeducted) {
                if (in_array($expense->expense_category, ['driver_advance', 'driver_allowance', 'route_advance'])) {
                    $deductions[] = [
                        'deduction_type' => OwnerSettlementDeduction::TYPE_ADVANCE,
                        'amount' => (float) $expense->amount,
                        'reference_type' => TripExpense::class,
                        'reference_id' => $expense->id,
                        'description' => "Trip advance recovery (Voucher: {$expense->voucher_number})",
                    ];
                } elseif (in_array($expense->expense_category, ['emergency_repair', 'emergency_repairs'])) {
                    $deductions[] = [
                        'deduction_type' => OwnerSettlementDeduction::TYPE_REPAIR,
                        'amount' => (float) $expense->amount,
                        'reference_type' => TripExpense::class,
                        'reference_id' => $expense->id,
                        'description' => "En-route emergency repair borne by company (Voucher: {$expense->voucher_number})",
                    ];
                }
            }
        }

        // 3. Workshop Job Cards on this vehicle during trip window
        $jobCards = JobCard::where('company_id', $trip->company_id)
            ->where('vehicle_id', $vehicle->id)
            ->where('status', JobCard::STATUS_COMPLETED)
            ->whereDate('completed_at', '>=', $trip->trip_date)
            ->get();

        foreach ($jobCards as $job) {
            $alreadyDeducted = OwnerSettlementDeduction::where('company_id', $trip->company_id)
                ->where('reference_type', JobCard::class)
                ->where('reference_id', $job->id)
                ->whereHas('settlement', function ($sq) {
                    $sq->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED]);
                })
                ->exists();

            if (!$alreadyDeducted) {
                $deductions[] = [
                    'deduction_type' => OwnerSettlementDeduction::TYPE_REPAIR,
                    'amount' => (float) $job->total_cost,
                    'reference_type' => JobCard::class,
                    'reference_id' => $job->id,
                    'description' => "Workshop maintenance borne by company (Job Card: {$job->job_card_number})",
                ];
            }
        }

        return $deductions;
    }

    /**
     * Create a third-party vehicle owner settlement.
     * Prevents duplicate settlements for the same trip and prevents duplicate deductions.
     */
    public function createSettlement(array $data, ?User $user = null): OwnerSettlement
    {
        $companyId = $data['company_id'];
        $tripId = $data['trip_id'] ?? null;

        // Prevent duplicate active settlement for the same trip
        if ($tripId) {
            $existing = OwnerSettlement::where('company_id', $companyId)
                ->where('trip_id', $tripId)
                ->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED])
                ->first();

            if ($existing) {
                throw new \LogicException("An active settlement ({$existing->settlement_number}) already exists for this trip.");
            }
        }

        // Validate deduction references to prevent double deductions
        $deductions = $data['deductions'] ?? [];
        foreach ($deductions as $ded) {
            if (!empty($ded['reference_type']) && !empty($ded['reference_id'])) {
                $duplicate = OwnerSettlementDeduction::where('company_id', $companyId)
                    ->where('reference_type', $ded['reference_type'])
                    ->where('reference_id', $ded['reference_id'])
                    ->whereHas('settlement', function ($sq) {
                        $sq->whereNotIn('status', [OwnerSettlement::STATUS_CANCELLED, OwnerSettlement::STATUS_REVERSED]);
                    })
                    ->exists();

                if ($duplicate) {
                    throw new \LogicException("Deduction item [{$ded['description']}] has already been deducted in another settlement.");
                }
            }
        }

        return DB::transaction(function () use ($data, $companyId, $user, $deductions) {
            $settlementNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_OWNER_SETTLEMENT,
                null,
                5,
                $companyId,
                $data['branch_id'] ?? null
            );

            // Compute deduction sums
            $fuelDeduction = 0.00;
            $repairDeduction = 0.00;
            $advanceDeduction = 0.00;
            $penaltyDeduction = 0.00;
            $otherDeductions = 0.00;

            foreach ($deductions as $d) {
                $amt = (float) ($d['amount'] ?? 0);
                switch ($d['deduction_type'] ?? 'other') {
                    case OwnerSettlementDeduction::TYPE_FUEL:
                        $fuelDeduction += $amt;
                        break;
                    case OwnerSettlementDeduction::TYPE_REPAIR:
                        $repairDeduction += $amt;
                        break;
                    case OwnerSettlementDeduction::TYPE_ADVANCE:
                        $advanceDeduction += $amt;
                        break;
                    case OwnerSettlementDeduction::TYPE_PENALTY:
                        $penaltyDeduction += $amt;
                        break;
                    default:
                        $otherDeductions += $amt;
                        break;
                }
            }

            $totalDeductions = round($fuelDeduction + $repairDeduction + $advanceDeduction + $penaltyDeduction + $otherDeductions, 2);
            $agreedPayable = (float) ($data['agreed_owner_payable'] ?? 0.00);
            $additionalCharges = (float) ($data['additional_charges'] ?? 0.00);
            $netPayable = round($agreedPayable - $totalDeductions + $additionalCharges, 2);

            $settlement = OwnerSettlement::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'vehicle_owner_id' => $data['vehicle_owner_id'],
                'vehicle_id' => $data['vehicle_id'],
                'trip_id' => $data['trip_id'] ?? null,
                'owner_contract_id' => $data['owner_contract_id'] ?? null,
                'settlement_number' => $settlementNumber,
                'settlement_date' => $data['settlement_date'] ?? now()->toDateString(),
                'payment_model' => $data['payment_model'] ?? OwnerSettlement::MODEL_FIXED_PER_TRIP,
                'customer_freight_total' => $data['customer_freight_total'] ?? 0.00,
                'agreed_owner_payable' => $agreedPayable,
                'fuel_deduction' => $fuelDeduction,
                'repair_deduction' => $repairDeduction,
                'advance_deduction' => $advanceDeduction,
                'penalty_deduction' => $penaltyDeduction,
                'other_deductions' => $otherDeductions,
                'total_deductions' => $totalDeductions,
                'additional_charges' => $additionalCharges,
                'net_payable' => $netPayable,
                'paid_amount' => 0.00,
                'balance_due' => $netPayable,
                'contract_snapshot' => $data['contract_snapshot'] ?? null,
                'calculation_details' => $data['calculation_details'] ?? null,
                'status' => OwnerSettlement::STATUS_DRAFT,
                'payment_status' => OwnerSettlement::PAYMENT_UNPAID,
                'prepared_by' => $user?->id,
                'notes' => $data['notes'] ?? null,
            ]);

            // Save deduction line items
            foreach ($deductions as $d) {
                OwnerSettlementDeduction::create([
                    'company_id' => $companyId,
                    'owner_settlement_id' => $settlement->id,
                    'deduction_type' => $d['deduction_type'] ?? 'other',
                    'amount' => (float) ($d['amount'] ?? 0),
                    'reference_type' => $d['reference_type'] ?? null,
                    'reference_id' => $d['reference_id'] ?? null,
                    'description' => $d['description'] ?? 'Approved deduction',
                ]);
            }

            return $settlement;
        });
    }

    /**
     * Submit settlement for review.
     */
    public function submitSettlement(OwnerSettlement $settlement, ?User $user = null): OwnerSettlement
    {
        if (!$settlement->canSubmit()) {
            throw new \LogicException("Settlement {$settlement->settlement_number} cannot be submitted in status {$settlement->status}.");
        }

        $settlement->status = OwnerSettlement::STATUS_SUBMITTED;
        $settlement->save();

        return $settlement;
    }

    /**
     * Approve settlement.
     */
    public function approveSettlement(OwnerSettlement $settlement, ?User $user = null): OwnerSettlement
    {
        if (!$settlement->canApprove()) {
            throw new \LogicException("Settlement {$settlement->settlement_number} cannot be approved from status {$settlement->status}.");
        }

        $settlement->status = OwnerSettlement::STATUS_APPROVED;
        $settlement->approved_by = $user?->id;
        $settlement->approved_at = now();
        $settlement->save();

        return $settlement;
    }

    /**
     * Post settlement to the owner ledger.
     * Guaranteed immutable once posted.
     */
    public function postSettlement(OwnerSettlement $settlement, ?User $user = null): OwnerSettlement
    {
        return DB::transaction(function () use ($settlement, $user) {
            /** @var OwnerSettlement $locked */
            $locked = OwnerSettlement::where('id', $settlement->id)->lockForUpdate()->first();

            if ($locked->isPosted()) {
                throw new \LogicException("Settlement {$locked->settlement_number} is already posted.");
            }

            if (!$locked->canPost()) {
                throw new \LogicException("Settlement {$locked->settlement_number} must be approved before posting.");
            }

            $locked->status = OwnerSettlement::STATUS_POSTED;
            $locked->posted_by = $user?->id;
            $locked->posted_at = now();
            $locked->save();

            // 1. Ledger Entry: Credit Gross Trip Payable
            $this->ledgerService->recordEntry(
                companyId: $locked->company_id,
                vehicleOwnerId: $locked->vehicle_owner_id,
                transactionType: OwnerLedger::TYPE_TRIP_PAYABLE,
                entryType: OwnerLedger::ENTRY_CREDIT,
                amount: (float) $locked->agreed_owner_payable,
                description: "Trip Freight Payable (Settlement: {$locked->settlement_number})",
                transactionDate: $locked->settlement_date->toDateString(),
                referenceType: OwnerSettlement::class,
                referenceId: $locked->id,
                referenceNumber: $locked->settlement_number,
                tripId: $locked->trip_id,
                branchId: $locked->branch_id,
                userId: $user?->id
            );

            // 2. Ledger Entries: Deductions (Debits decreasing amount company owes)
            foreach ($locked->deductionItems as $item) {
                $transactionType = OwnerLedger::TYPE_DEDUCTION;
                if ($item->deduction_type === OwnerSettlementDeduction::TYPE_ADVANCE) {
                    $transactionType = OwnerLedger::TYPE_ADVANCE;
                }

                $this->ledgerService->recordEntry(
                    companyId: $locked->company_id,
                    vehicleOwnerId: $locked->vehicle_owner_id,
                    transactionType: $transactionType,
                    entryType: OwnerLedger::ENTRY_DEBIT,
                    amount: (float) $item->amount,
                    description: "Deduction: {$item->description} ({$locked->settlement_number})",
                    transactionDate: $locked->settlement_date->toDateString(),
                    referenceType: OwnerSettlementDeduction::class,
                    referenceId: $item->id,
                    referenceNumber: $locked->settlement_number,
                    tripId: $locked->trip_id,
                    branchId: $locked->branch_id,
                    userId: $user?->id
                );
            }

            // 3. Ledger Entry: Additional Charges (Credit increasing payable)
            if ($locked->additional_charges > 0) {
                $this->ledgerService->recordEntry(
                    companyId: $locked->company_id,
                    vehicleOwnerId: $locked->vehicle_owner_id,
                    transactionType: OwnerLedger::TYPE_ADDITIONAL_CHARGE,
                    entryType: OwnerLedger::ENTRY_CREDIT,
                    amount: (float) $locked->additional_charges,
                    description: "Additional allowance / charges ({$locked->settlement_number})",
                    transactionDate: $locked->settlement_date->toDateString(),
                    referenceType: OwnerSettlement::class,
                    referenceId: $locked->id,
                    referenceNumber: $locked->settlement_number,
                    tripId: $locked->trip_id,
                    branchId: $locked->branch_id,
                    userId: $user?->id
                );
            }

            // Mark trip financial status
            if ($locked->trip) {
                $locked->trip->financial_status = Trip::FINANCIAL_SETTLED;
                $locked->trip->financially_closed_at = now();
                $locked->trip->save();
            }

            return $locked->fresh(['deductionItems', 'owner', 'vehicle', 'trip']);
        });
    }

    /**
     * Reverse a posted settlement.
     * Guarantees audit compliance without silently modifying historical records.
     */
    public function reverseSettlement(
        OwnerSettlement $settlement,
        string $reason,
        ?User $user = null
    ): OwnerSettlement {
        return DB::transaction(function () use ($settlement, $reason, $user) {
            /** @var OwnerSettlement $locked */
            $locked = OwnerSettlement::where('id', $settlement->id)->lockForUpdate()->first();

            if (!$locked->canReverse()) {
                throw new \LogicException("Cannot reverse settlement {$locked->settlement_number} in status {$locked->status}.");
            }

            if ($locked->paid_amount > 0) {
                throw new \LogicException("Cannot reverse settlement {$locked->settlement_number} because payments of PKR {$locked->paid_amount} have been recorded against it. Cancel or adjust payments first.");
            }

            // 1. Reversal Debit for Trip Payable
            $this->ledgerService->recordEntry(
                companyId: $locked->company_id,
                vehicleOwnerId: $locked->vehicle_owner_id,
                transactionType: OwnerLedger::TYPE_REVERSAL,
                entryType: OwnerLedger::ENTRY_DEBIT,
                amount: (float) $locked->agreed_owner_payable,
                description: "REVERSAL of Trip Payable ({$locked->settlement_number}): {$reason}",
                transactionDate: now()->toDateString(),
                referenceType: OwnerSettlement::class,
                referenceId: $locked->id,
                referenceNumber: 'REV-' . $locked->settlement_number,
                tripId: $locked->trip_id,
                branchId: $locked->branch_id,
                userId: $user?->id
            );

            // 2. Reversal Credit for each deducted item (restoring deductions to owner)
            foreach ($locked->deductionItems as $item) {
                $this->ledgerService->recordEntry(
                    companyId: $locked->company_id,
                    vehicleOwnerId: $locked->vehicle_owner_id,
                    transactionType: OwnerLedger::TYPE_REVERSAL,
                    entryType: OwnerLedger::ENTRY_CREDIT,
                    amount: (float) $item->amount,
                    description: "REVERSAL of Deduction: {$item->description} ({$locked->settlement_number})",
                    transactionDate: now()->toDateString(),
                    referenceType: OwnerSettlementDeduction::class,
                    referenceId: $item->id,
                    referenceNumber: 'REV-' . $locked->settlement_number,
                    tripId: $locked->trip_id,
                    branchId: $locked->branch_id,
                    userId: $user?->id
                );
            }

            // 3. Reversal Debit for Additional Charges (if any)
            if ($locked->additional_charges > 0) {
                $this->ledgerService->recordEntry(
                    companyId: $locked->company_id,
                    vehicleOwnerId: $locked->vehicle_owner_id,
                    transactionType: OwnerLedger::TYPE_REVERSAL,
                    entryType: OwnerLedger::ENTRY_DEBIT,
                    amount: (float) $locked->additional_charges,
                    description: "REVERSAL of Additional Charges ({$locked->settlement_number}): {$reason}",
                    transactionDate: now()->toDateString(),
                    referenceType: OwnerSettlement::class,
                    referenceId: $locked->id,
                    referenceNumber: 'REV-' . $locked->settlement_number,
                    tripId: $locked->trip_id,
                    branchId: $locked->branch_id,
                    userId: $user?->id
                );
            }

            $locked->status = OwnerSettlement::STATUS_REVERSED;
            $locked->reversed_by = $user?->id;
            $locked->reversed_at = now();
            $locked->reversal_reason = $reason;
            $locked->save();

            // Reset trip financial status
            if ($locked->trip) {
                $locked->trip->financial_status = Trip::FINANCIAL_PENDING;
                $locked->trip->financially_closed_at = null;
                $locked->trip->save();
            }

            return $locked->fresh();
        });
    }

    /**
     * Record a payment to an attached vehicle owner (partial or full).
     */
    public function recordOwnerPayment(array $paymentData, ?User $user = null): OwnerPayment
    {
        $companyId = $paymentData['company_id'];
        $ownerId = $paymentData['vehicle_owner_id'];
        $amount = (float) $paymentData['amount'];

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($paymentData, $companyId, $ownerId, $amount, $user) {
            $settlement = null;
            if (!empty($paymentData['owner_settlement_id'])) {
                /** @var OwnerSettlement $settlement */
                $settlement = OwnerSettlement::where('company_id', $companyId)
                    ->where('id', $paymentData['owner_settlement_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$settlement) {
                    throw new \InvalidArgumentException('Selected owner settlement not found.');
                }

                if (!$settlement->isPosted()) {
                    throw new \LogicException('Cannot record payment against an unposted settlement.');
                }

                if ($amount > $settlement->balance_due + 0.01) {
                    throw new \LogicException("Payment amount (PKR {$amount}) exceeds outstanding settlement balance (PKR {$settlement->balance_due}).");
                }
            }

            $paymentNumber = $this->sequenceService->getNextSequenceNumber(
                DocumentSequence::TYPE_PAYMENT,
                null,
                5,
                $companyId,
                $paymentData['branch_id'] ?? null
            );

            $payment = OwnerPayment::create([
                'company_id' => $companyId,
                'branch_id' => $paymentData['branch_id'] ?? null,
                'vehicle_owner_id' => $ownerId,
                'owner_settlement_id' => $settlement?->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $paymentData['payment_date'] ?? now()->toDateString(),
                'amount' => $amount,
                'payment_method' => $paymentData['payment_method'] ?? OwnerPayment::METHOD_BANK_TRANSFER,
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'cheque_number' => $paymentData['cheque_number'] ?? null,
                'cheque_date' => $paymentData['cheque_date'] ?? null,
                'bank_name' => $paymentData['bank_name'] ?? null,
                'bank_account_number' => $paymentData['bank_account_number'] ?? null,
                'status' => OwnerPayment::STATUS_COMPLETED,
                'created_by' => $user?->id,
                'notes' => $paymentData['notes'] ?? null,
            ]);

            // Update settlement paid amount and status if applicable
            if ($settlement) {
                $settlement->paid_amount += $amount;
                $settlement->recalculateTotals();
            }

            // Write to Owner Ledger: Debit decreases liability
            $methodLabel = strtoupper(str_replace('_', ' ', $payment->payment_method));
            $description = "Owner Payment ({$methodLabel}): {$paymentNumber}";
            if ($settlement) {
                $description .= " against {$settlement->settlement_number}";
            }

            $this->ledgerService->recordEntry(
                companyId: $companyId,
                vehicleOwnerId: $ownerId,
                transactionType: OwnerLedger::TYPE_PAYMENT,
                entryType: OwnerLedger::ENTRY_DEBIT,
                amount: $amount,
                description: $description,
                transactionDate: $payment->payment_date->toDateString(),
                referenceType: OwnerPayment::class,
                referenceId: $payment->id,
                referenceNumber: $paymentNumber,
                tripId: $settlement?->trip_id,
                branchId: $payment->branch_id,
                userId: $user?->id
            );

            return $payment->fresh(['settlement', 'owner']);
        });
    }
}

