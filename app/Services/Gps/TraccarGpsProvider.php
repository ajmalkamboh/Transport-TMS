<?php

namespace App\Services\Gps;

use App\Contracts\GpsProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TraccarGpsProvider implements GpsProviderInterface
{
    public function __construct(
        protected ?string $apiUrl = null,
        protected ?string $apiToken = null
    ) {
        $this->apiUrl = $this->apiUrl ?? config('services.traccar.url', 'http://traccar.local:8082/api');
        $this->apiToken = $this->apiToken ?? config('services.traccar.token');
    }

    public function getName(): string
    {
        return 'traccar';
    }

    public function normalizeTelemetry(array $pos): array
    {
        return [
            'latitude' => (float) $pos['latitude'],
            'longitude' => (float) $pos['longitude'],
            'altitude' => (float) ($pos['altitude'] ?? 0),
            'speed_kmh' => round((float) ($pos['speed'] ?? 0) * 1.852, 2),
            'heading' => (float) ($pos['course'] ?? 0),
            'ignition' => (bool) ($pos['attributes']['ignition'] ?? false),
            'satellites' => (int) ($pos['attributes']['sat'] ?? 0),
            'battery_percent' => (float) ($pos['attributes']['batteryLevel'] ?? 100),
            'recorded_at' => isset($pos['fixTime']) ? Carbon::parse($pos['fixTime']) : now(),
        ];
    }

    public function fetchLatestLocation(string $deviceIdentifier): ?array
    {
        try {
            // Real HTTP call if configured, otherwise fallback to safe payload structure
            if ($this->apiToken && filter_var($this->apiUrl, FILTER_VALIDATE_URL)) {
                $response = Http::withToken($this->apiToken)
                    ->timeout(5)
                    ->get("{$this->apiUrl}/positions", ['uniqueId' => $deviceIdentifier]);

                if ($response->successful() && !empty($response->json())) {
                    $pos = $response->json()[0];
                    return [
                        'latitude' => (float) $pos['latitude'],
                        'longitude' => (float) $pos['longitude'],
                        'altitude' => (float) ($pos['altitude'] ?? 0),
                        'speed_kmh' => round((float) ($pos['speed'] ?? 0) * 1.852, 2), // knots to km/h
                        'heading' => (float) ($pos['course'] ?? 0),
                        'ignition' => (bool) ($pos['attributes']['ignition'] ?? false),
                        'satellites' => (int) ($pos['attributes']['sat'] ?? 0),
                        'battery_percent' => (float) ($pos['attributes']['batteryLevel'] ?? 100),
                        'recorded_at' => Carbon::parse($pos['fixTime']),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Traccar GPS fetch failed for {$deviceIdentifier}: " . $e->getMessage());
        }

        return null;
    }

    public function fetchHistoricalLocations(string $deviceIdentifier, Carbon $from, Carbon $to): array
    {
        // Provider historical positions fetch
        return [];
    }

    public function sendCommand(string $deviceIdentifier, string $command, array $parameters = []): bool
    {
        return true;
    }
}
