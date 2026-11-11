<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardItem;
use App\Models\StockAdjustment;
use App\Models\StockLedgerEntry;
use App\Models\StockTransfer;
use App\Models\UnitOfMeasurement;
use App\Models\UomConversion;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function __construct(
        protected GeneralLedgerService $generalLedgerService,
        protected DocumentSequenceService $sequenceService,
        protected AuditLogService $auditLog,
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Get or initialize a warehouse stock record.
     */
    public function getOrCreateWarehouseStock(int $companyId, int $warehouseId, int $itemId): WarehouseStock
    {
        return WarehouseStock::withoutGlobalScopes()->firstOrCreate(
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'inventory_item_id' => $itemId,
            ],
            [
                'quantity_on_hand' => 0,
                'quantity_allocated' => 0,
                'average_unit_cost' => 0,
                'total_valuation' => 0,
            ]
        );
    }

    /**
     * Get available stock in a warehouse.
     */
    public function getAvailableStock(int $warehouseId, int $itemId): float
    {
        $stock = WarehouseStock::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)
            ->first();

        return $stock ? (float) $stock->available_quantity : 0.0;
    }

    /**
     * Validate sufficient stock availability and prevent negative stock.
     */
    public function validateStockAvailability(int $warehouseId, int $itemId, float $requiredQuantity): WarehouseStock
    {
        $stock = WarehouseStock::withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('inventory_item_id', $itemId)
            ->first();

        $available = $stock ? (float) $stock->quantity_on_hand : 0.0;

        if (!$stock || $available < $requiredQuantity) {
            $item = InventoryItem::withoutGlobalScopes()->find($itemId);
            $itemName = $item ? $item->name : "Item #{$itemId}";
            throw new InvalidArgumentException(
                "Insufficient stock for [{$itemName}]. Available: {$available}, Requested: {$requiredQuantity}. Negative stock is prohibited."
            );
        }

        return $stock;
    }

    /**
     * Convert quantity between units of measurement.
     */
    public function convertQuantity(int $companyId, int $fromUomId, int $toUomId, float $quantity): float
    {
        if ($fromUomId === $toUomId) {
            return $quantity;
        }

        // Direct conversion
        $direct = UomConversion::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->first();

        if ($direct) {
            return round($quantity * (float) $direct->conversion_factor, 4);
        }

        // Reverse conversion
        $reverse = UomConversion::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->first();

        if ($reverse && (float) $reverse->conversion_factor > 0) {
            return round($quantity / (float) $reverse->conversion_factor, 4);
        }

        throw new InvalidArgumentException("No unit conversion defined between UOM #{$fromUomId} and UOM #{$toUomId}.");
    }

    /**
     * Receive stock into a warehouse (GRN, opening stock, transfer-in, adjustment gain).
     * Applies Weighted Average Cost (WAC) engine.
     */
    public function receiveStock(
        int $companyId,
        int $warehouseId,
        int $itemId,
        float $quantity,
        float $unitCost,
        string $transactionType,
        ?string $refType = null,
        ?int $refId = null,
        ?string $refNumber = null,
        ?User $user = null,
        ?string $notes = null
    ): StockLedgerEntry {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Received quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $companyId, $warehouseId, $itemId, $quantity, $unitCost,
            $transactionType, $refType, $refId, $refNumber, $user, $notes
        ) {
            $stock = $this->getOrCreateWarehouseStock($companyId, $warehouseId, $itemId);

            $currentQty = (float) $stock->quantity_on_hand;
            $currentWac = (float) $stock->average_unit_cost;
            $currentValuation = (float) $stock->total_valuation;

            // Weighted Average Cost (WAC) formula:
            $incomingValuation = $quantity * $unitCost;
            $newQty = $currentQty + $quantity;
            $newValuation = $currentValuation + $incomingValuation;
            $newWac = $newQty > 0 ? round($newValuation / $newQty, 4) : $unitCost;

            $stock->update([
                'quantity_on_hand' => $newQty,
                'average_unit_cost' => $newWac,
                'total_valuation' => $newValuation,
            ]);

            // Update master item costs
            $item = InventoryItem::withoutGlobalScopes()->find($itemId);
            if ($item) {
                $item->update([
                    'average_cost' => $newWac,
                    'last_purchase_cost' => $unitCost,
                ]);
            }

            // Create Immutable Stock Ledger Entry
            return StockLedgerEntry::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'inventory_item_id' => $itemId,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => $transactionType,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_number' => $refNumber,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'unit_cost' => $unitCost,
                'total_cost' => $incomingValuation,
                'balance_quantity' => $newQty,
                'balance_valuation' => $newValuation,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Issue stock out of a warehouse (Job Card, general issue, transfer-out, purchase return, wastage).
     * Enforces negative stock prohibition and issues at current WAC.
     */
    public function issueStock(
        int $companyId,
        int $warehouseId,
        int $itemId,
        float $quantity,
        string $transactionType,
        ?string $refType = null,
        ?int $refId = null,
        ?string $refNumber = null,
        ?User $user = null,
        ?string $notes = null
    ): StockLedgerEntry {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Issued quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $companyId, $warehouseId, $itemId, $quantity,
            $transactionType, $refType, $refId, $refNumber, $user, $notes
        ) {
            $stock = $this->validateStockAvailability($warehouseId, $itemId, $quantity);

            $currentQty = (float) $stock->quantity_on_hand;
            $unitCost = (float) $stock->average_unit_cost;
            $issuedValuation = round($quantity * $unitCost, 2);

            $newQty = $currentQty - $quantity;
            $newValuation = round($newQty * $unitCost, 2);

            $stock->update([
                'quantity_on_hand' => $newQty,
                'total_valuation' => $newValuation,
            ]);

            return StockLedgerEntry::create([
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'inventory_item_id' => $itemId,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => $transactionType,
                'reference_type' => $refType,
                'reference_id' => $refId,
                'reference_number' => $refNumber,
                'quantity_in' => 0,
                'quantity_out' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $issuedValuation,
                'balance_quantity' => $newQty,
                'balance_valuation' => $newValuation,
                'user_id' => $user?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Issue spare parts to a Vehicle Maintenance Job Card.
     * Decrements inventory, updates JobCard parts total, and posts maintenance expense vs inventory asset.
     */
    public function issuePartsToJobCard(JobCard $jobCard, array $itemsData, ?User $user = null): array
    {
        return DB::transaction(function () use ($jobCard, $itemsData, $user) {
            $createdItems = [];
            $totalCostIssued = 0;

            foreach ($itemsData as $data) {
                $itemId = $data['inventory_item_id'];
                $warehouseId = $data['warehouse_id'];
                $qty = (float) $data['quantity'];
                $notes = $data['notes'] ?? null;

                $item = InventoryItem::withoutGlobalScopes()->findOrFail($itemId);

                // 1. Issue stock from warehouse (validates negative stock)
                $ledgerEntry = $this->issueStock(
                    companyId: $jobCard->company_id,
                    warehouseId: $warehouseId,
                    itemId: $itemId,
                    quantity: $qty,
                    transactionType: StockLedgerEntry::TYPE_ISSUE_MAINTENANCE,
                    refType: JobCard::class,
                    refId: $jobCard->id,
                    refNumber: $jobCard->job_card_number,
                    user: $user,
                    notes: "Issued for Job Card #{$jobCard->job_card_number} (Vehicle: " . ($jobCard->vehicle?->registration_number ?? 'Fleet') . ")"
                );

                $unitCost = (float) $ledgerEntry->unit_cost;
                $totalCost = (float) $ledgerEntry->total_cost;
                $totalCostIssued += $totalCost;

                // 2. Create Job Card Item record
                $jobCardItem = JobCardItem::create([
                    'company_id' => $jobCard->company_id,
                    'job_card_id' => $jobCard->id,
                    'inventory_item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'part_name' => $item->name,
                    'part_number' => $item->item_code,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'is_inventory_item' => true,
                    'issued_by' => $user?->id,
                    'notes' => $notes,
                ]);

                $createdItems[] = $jobCardItem;
            }

            // 3. Update Job Card total costs
            $newPartsTotal = (float) $jobCard->items()->sum('total_cost');
            $newTotalCost = (float) $jobCard->labour_charges + $newPartsTotal + (float) $jobCard->tax_amount;

            $jobCard->update([
                'parts_total_cost' => $newPartsTotal,
                'total_cost' => $newTotalCost,
            ]);

            // 4. Post Double-Entry Accounting Voucher:
            // Dr. 5400-VEHICLE-MAINTENANCE (Expense)
            // Cr. 1300-SPARES-INVENTORY (Asset)
            if ($totalCostIssued > 0) {
                $maintenanceAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $jobCard->company_id)
                    ->where('account_code', ChartOfAccount::CODE_VEHICLE_MAINTENANCE)
                    ->first();

                $inventoryAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $jobCard->company_id)
                    ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
                    ->first();

                if ($maintenanceAccount && $inventoryAccount) {
                    $this->generalLedgerService->postVoucher([
                        'company_id' => $jobCard->company_id,
                        'branch_id' => $jobCard->branch_id,
                        'voucher_type' => 'journal',
                        'voucher_date' => now()->toDateString(),
                        'reference_number' => $jobCard->job_card_number,
                        'description' => "Spare parts consumption for Job Card #{$jobCard->job_card_number} (Vehicle: " . ($jobCard->vehicle?->registration_number ?? 'Fleet') . ")",
                        'idempotency_key' => "JC-PARTS-{$jobCard->id}-" . uniqid(),
                        'items' => [
                            [
                                'chart_of_account_id' => $maintenanceAccount->id,
                                'debit' => $totalCostIssued,
                                'credit' => 0,
                                'narration' => "Maintenance parts consumed for {$jobCard->job_card_number}",
                                'tracking_type' => 'Vehicle',
                                'tracking_id' => $jobCard->vehicle_id,
                            ],
                            [
                                'chart_of_account_id' => $inventoryAccount->id,
                                'debit' => 0,
                                'credit' => $totalCostIssued,
                                'narration' => "Inventory parts issued to Job Card {$jobCard->job_card_number}",
                            ],
                        ],
                    ], $user);
                }
            }

            $this->auditLog->log('job_card_parts_issued', $jobCard, null, [
                'items_count' => count($createdItems),
                'total_cost' => $totalCostIssued,
            ], "Issued " . count($createdItems) . " spare parts to Job Card {$jobCard->job_card_number}");

            return $createdItems;
        });
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transferStock(StockTransfer $transfer, ?User $user = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            $totalCost = 0;

            foreach ($transfer->items as $item) {
                // Issue from source warehouse
                $outEntry = $this->issueStock(
                    companyId: $transfer->company_id,
                    warehouseId: $transfer->from_warehouse_id,
                    itemId: $item->inventory_item_id,
                    quantity: (float) $item->quantity_transferred,
                    transactionType: StockLedgerEntry::TYPE_TRANSFER_OUT,
                    refType: StockTransfer::class,
                    refId: $transfer->id,
                    refNumber: $transfer->transfer_number,
                    user: $user,
                    notes: "Transferred to " . ($transfer->toWarehouse?->name ?? "Warehouse #{$transfer->to_warehouse_id}")
                );

                $unitCost = (float) $outEntry->unit_cost;
                $lineTotal = round((float) $item->quantity_transferred * $unitCost, 2);
                $totalCost += $lineTotal;

                $item->update([
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                    'quantity_received' => $item->quantity_transferred,
                ]);

                // Receive into destination warehouse at same unit cost
                $this->receiveStock(
                    companyId: $transfer->company_id,
                    warehouseId: $transfer->to_warehouse_id,
                    itemId: $item->inventory_item_id,
                    quantity: (float) $item->quantity_transferred,
                    unitCost: $unitCost,
                    transactionType: StockLedgerEntry::TYPE_TRANSFER_IN,
                    refType: StockTransfer::class,
                    refId: $transfer->id,
                    refNumber: $transfer->transfer_number,
                    user: $user,
                    notes: "Transferred from " . ($transfer->fromWarehouse?->name ?? "Warehouse #{$transfer->from_warehouse_id}")
                );
            }

            $transfer->update([
                'status' => StockTransfer::STATUS_COMPLETED,
                'total_cost' => $totalCost,
                'dispatched_by' => $user?->id,
                'dispatched_at' => now(),
                'received_by' => $user?->id,
                'received_at' => now(),
            ]);

            return $transfer;
        });
    }

    /**
     * Adjust stock (Gain, Loss, Damage, Wastage, Physical Count).
     */
    public function adjustStock(StockAdjustment $adjustment, ?User $user = null): StockAdjustment
    {
        return DB::transaction(function () use ($adjustment, $user) {
            $netDifferenceCost = 0;
            $lossCost = 0;
            $gainCost = 0;

            foreach ($adjustment->items as $adjItem) {
                $stock = $this->getOrCreateWarehouseStock($adjustment->company_id, $adjustment->warehouse_id, $adjItem->inventory_item_id);
                $currentWac = (float) $stock->average_unit_cost;
                $diff = (float) $adjItem->quantity_difference;

                $adjItem->update([
                    'unit_cost' => $currentWac,
                    'total_difference_cost' => round($diff * $currentWac, 2),
                ]);

                if ($diff > 0) {
                    // Surplus / Gain
                    $gainCost += ($diff * $currentWac);
                    $this->receiveStock(
                        companyId: $adjustment->company_id,
                        warehouseId: $adjustment->warehouse_id,
                        itemId: $adjItem->inventory_item_id,
                        quantity: $diff,
                        unitCost: $currentWac,
                        transactionType: $adjustment->adjustment_type === StockAdjustment::TYPE_PHYSICAL_COUNT
                            ? StockLedgerEntry::TYPE_PHYSICAL_COUNT
                            : StockLedgerEntry::TYPE_ADJUSTMENT_GAIN,
                        refType: StockAdjustment::class,
                        refId: $adjustment->id,
                        refNumber: $adjustment->adjustment_number,
                        user: $user,
                        notes: $adjustment->reason
                    );
                } elseif ($diff < 0) {
                    // Deficit / Loss / Damage / Wastage
                    $absDiff = abs($diff);
                    $lossCost += ($absDiff * $currentWac);
                    $transType = match ($adjustment->adjustment_type) {
                        StockAdjustment::TYPE_DAMAGE => StockLedgerEntry::TYPE_DAMAGE,
                        StockAdjustment::TYPE_WASTAGE => StockLedgerEntry::TYPE_WASTAGE,
                        StockAdjustment::TYPE_PHYSICAL_COUNT => StockLedgerEntry::TYPE_PHYSICAL_COUNT,
                        default => StockLedgerEntry::TYPE_ADJUSTMENT_LOSS,
                    };

                    $this->issueStock(
                        companyId: $adjustment->company_id,
                        warehouseId: $adjustment->warehouse_id,
                        itemId: $adjItem->inventory_item_id,
                        quantity: $absDiff,
                        transactionType: $transType,
                        refType: StockAdjustment::class,
                        refId: $adjustment->id,
                        refNumber: $adjustment->adjustment_number,
                        user: $user,
                        notes: $adjustment->reason
                    );
                }
            }

            $adjustment->update([
                'status' => StockAdjustment::STATUS_APPROVED,
                'total_cost' => $lossCost - $gainCost,
                'approved_by' => $user?->id,
                'approved_at' => now(),
            ]);

            // Post General Ledger entry for inventory write-off (if loss/damage/wastage)
            if ($lossCost > 0) {
                $writeOffAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $adjustment->company_id)
                    ->where('account_code', ChartOfAccount::CODE_INVENTORY_WRITE_OFF)
                    ->first();

                $inventoryAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where('company_id', $adjustment->company_id)
                    ->where('account_code', ChartOfAccount::CODE_INVENTORY_SPARES)
                    ->first();

                if ($writeOffAccount && $inventoryAccount) {
                    $jv = $this->generalLedgerService->postVoucher([
                        'company_id' => $adjustment->company_id,
                        'voucher_type' => 'adjustment',
                        'voucher_date' => $adjustment->adjustment_date->toDateString(),
                        'reference_number' => $adjustment->adjustment_number,
                        'description' => "Stock adjustment write-off: {$adjustment->reason}",
                        'idempotency_key' => "ADJ-{$adjustment->id}-" . uniqid(),
                        'items' => [
                            [
                                'chart_of_account_id' => $writeOffAccount->id,
                                'debit' => $lossCost,
                                'credit' => 0,
                                'narration' => "Inventory write-off / shortage {$adjustment->adjustment_number}",
                            ],
                            [
                                'chart_of_account_id' => $inventoryAccount->id,
                                'debit' => 0,
                                'credit' => $lossCost,
                                'narration' => "Stock reduction for {$adjustment->adjustment_number}",
                            ],
                        ],
                    ], $user);

                    $adjustment->update(['journal_voucher_id' => $jv->id]);
                }
            }

            return $adjustment;
        });
    }
}

