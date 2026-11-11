<?php

namespace App\Services\Gps;

use App\Contracts\GpsProviderInterface;
use Carbon\Carbon;

class GenericGpsProvider implements GpsProviderInterface
{
    public function getName(): string
    {
        return 'generic';
    }

    public function fetchLatestLocation(string $deviceIdentifier): ?array
    {
        return null;
    }

    public function fetchHistoricalLocations(string $deviceIdentifier, Carbon $from, Carbon $to): array
    {
        return [];
    }

    public function sendCommand(string $deviceIdentifier, string $command, array $parameters = []): bool
    {
        return true;
    }
}

