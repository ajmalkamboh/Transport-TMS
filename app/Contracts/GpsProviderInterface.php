<?php

namespace App\Contracts;

use Carbon\Carbon;

interface GpsProviderInterface
{
    /**
     * Get unique provider identifier.
     */
    public function getName(): string;

    /**
     * Fetch latest real-time GPS telemetry from external provider gateway.
     */
    public function fetchLatestLocation(string $deviceIdentifier): ?array;

    /**
     * Fetch historical location telemetry within a time window.
     */
    public function fetchHistoricalLocations(string $deviceIdentifier, Carbon $from, Carbon $to): array;

    /**
     * Push device configuration parameters or immobilizer command.
     */
    public function sendCommand(string $deviceIdentifier, string $command, array $parameters = []): bool;
}

