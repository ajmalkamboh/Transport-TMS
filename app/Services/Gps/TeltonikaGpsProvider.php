<?php

namespace App\Services\Gps;

use App\Contracts\GpsProviderInterface;
use Carbon\Carbon;

class TeltonikaGpsProvider implements GpsProviderInterface
{
    public function getName(): string
    {
        return 'teltonika';
    }

    public function normalizeTelemetry(array $data): array
    {
        return [
            'latitude' => (float) ($data['lat'] ?? $data['latitude'] ?? 0),
            'longitude' => (float) ($data['lng'] ?? $data['longitude'] ?? 0),
            'altitude' => (float) ($data['alt'] ?? $data['altitude'] ?? 0),
            'speed_kmh' => (float) ($data['speed'] ?? $data['speed_kmh'] ?? 0),
            'heading' => (float) ($data['angle'] ?? $data['heading'] ?? 0),
            'ignition' => (bool) ($data['ignition'] ?? false),
            'recorded_at' => isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now(),
        ];
    }

    public function fetchLatestLocation(string $deviceIdentifier): ?array
    {
        // Teltonika FMx telematics adapter implementation
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
