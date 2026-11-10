<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAdvance;
use Illuminate\Support\Facades\DB;

class DriverOperationService
{
    public function __construct(
        protected DocumentSequenceService $sequenceService
    ) {}

    /**
     * Disburse an operational or trip cash advance to a driver.
     */
    public function disburseAdvance(
        Driver $driver,
        float $amount,
        string $purpose,
        string $paymentMethod = 'cash',
        ?string $reference = null,
        ?int $tripId = null,
        ?int $disbursedBy = null,
        ?string $notes = null
    ): DriverAdvance {
        return DB::transaction(function () use ($driver, $amount, $purpose, $paymentMethod, $reference, $tripId, $disbursedBy, $notes) {
            $year = date('Y');
            $advanceNum = $this->sequenceService->getNextSequenceNumber('payment', 'ADV', 5, $driver->company_id);

            $advance = DriverAdvance::create([
                'company_id' => $driver->company_id,
                'driver_id' => $driver->id,
                'advance_number' => $advanceNum,
                'date' => now()->toDateString(),
                'amount' => $amount,
                'purpose' => $purpose,
                'payment_method' => $paymentMethod,
                'reference' => $reference,
                'status' => 'disbursed',
                'settled_amount' => 0.00,
                'balance_amount' => $amount,
                'trip_id' => $tripId,
                'disbursed_by' => $disbursedBy ?? auth()->id() ?? 1,
                'notes' => $notes,
            ]);

            // Increment driver total advances balance
            $driver->total_advances_balance = (float) $driver->total_advances_balance + $amount;
            $driver->save();

            return $advance;
        });
    }

    /**
     * Settle an outstanding driver advance during trip reconciliation or salary payout.
     */
    public function settleAdvance(DriverAdvance $advance, float $amount): DriverAdvance
    {
        return DB::transaction(function () use ($advance, $amount) {
            $newSettled = (float) $advance->settled_amount + $amount;
            $newBalance = max(0.00, (float) $advance->amount - $newSettled);
            $status = $newBalance <= 0.00 ? 'settled' : 'partially_settled';

            $advance->update([
                'settled_amount' => $newSettled,
                'balance_amount' => $newBalance,
                'status' => $status,
            ]);

            // Deduct from driver's total advances balance
            $driver = $advance->driver;
            $driver->total_advances_balance = max(0.00, (float) $driver->total_advances_balance - $amount);
            $driver->save();

            return $advance;
        });
    }
}
