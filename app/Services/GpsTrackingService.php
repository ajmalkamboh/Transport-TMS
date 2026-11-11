<?php

namespace App\Services;

use App\Contracts\GpsProviderInterface;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\GpsDevice;
use App\Models\Trip;
use App\Models\TripRouteDeviation;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Services\Gps\GenericGpsProvider;
use App\Services\Gps\TeltonikaGpsProvider;
use App\Services\Gps\TraccarGpsProvider;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class GpsTrackingService
{
    /**
     * Resolve provider adapter.
     */
    public function resolveProvider(string $providerName = 'generic'): GpsProviderInterface
    {
        return match (strtolower($providerName)) {
            'traccar' => app(TraccarGpsProvider::class),
            'teltonika' => app(TeltonikaGpsProvider::class),
            default => app(GenericGpsProvider::class),
        };
    }

    /**
     * Ingest and record genuine vehicle location telemetry.
     */
    public function recordLocation(array $data): VehicleLocation
    {
        $location = VehicleLocation::create([
            'company_id' => $data['company_id'],
            'vehicle_id' => $data['vehicle_id'],
            'trip_id' => $data['trip_id'] ?? null,
            'gps_device_id' => $data['gps_device_id'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'altitude_meters' => $data['altitude_meters'] ?? null,
            'speed_kmh' => $data['speed_kmh'] ?? 0.00,
            'heading_degrees' => $data['heading_degrees'] ?? null,
            'ignition_status' => (bool) ($data['ignition_status'] ?? false),
            'satellites_count' => (int) ($data['satellites_count'] ?? 0),
            'odometer_km' => $data['odometer_km'] ?? null,
            'battery_level_percent' => $data['battery_level_percent'] ?? null,
            'raw_payload' => $data['raw_payload'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        // If GPS device is associated, update its last_ping_at & online status
        if (!empty($data['gps_device_id'])) {
            GpsDevice::withoutGlobalScopes()->where('id', $data['gps_device_id'])->update([
                'last_ping_at' => now(),
                'status' => GpsDevice::STATUS_ONLINE,
            ]);
        }

        // Trigger Geofence analysis
        $this->checkGeofenceTriggers($location);

        return $location;
    }

    /**
     * Check if telemetry triggers entry or exit of any registered geofences.
     */
    public function checkGeofenceTriggers(VehicleLocation $location): array
    {
        $eventsTriggered = [];
        $geofences = Geofence::withoutGlobalScopes()
            ->where('company_id', $location->company_id)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $fence) {
            $isInsideNow = $fence->containsPoint((float)$location->latitude, (float)$location->longitude);

            // Check previous state
            $lastEvent = GeofenceEvent::withoutGlobalScopes()
                ->where('company_id', $location->company_id)
                ->where('vehicle_id', $location->vehicle_id)
                ->where('geofence_id', $fence->id)
                ->latest('recorded_at')
                ->first();

            $wasInside = $lastEvent ? ($lastEvent->event_type === GeofenceEvent::EVENT_ENTRY) : false;

            if ($isInsideNow && !$wasInside && $fence->alert_on_entry) {
                $ev = GeofenceEvent::create([
                    'company_id' => $location->company_id,
                    'vehicle_id' => $location->vehicle_id,
                    'trip_id' => $location->trip_id,
                    'geofence_id' => $fence->id,
                    'event_type' => GeofenceEvent::EVENT_ENTRY,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'recorded_at' => $location->recorded_at,
                ]);
                $eventsTriggered[] = $ev;
            } elseif (!$isInsideNow && $wasInside && $fence->alert_on_exit) {
                $ev = GeofenceEvent::create([
                    'company_id' => $location->company_id,
                    'vehicle_id' => $location->vehicle_id,
                    'trip_id' => $location->trip_id,
                    'geofence_id' => $fence->id,
                    'event_type' => GeofenceEvent::EVENT_EXIT,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'recorded_at' => $location->recorded_at,
                ]);
                $eventsTriggered[] = $ev;
            }
        }

        return $eventsTriggered;
    }

    /**
     * Calculate straight-line or coordinate deviation from planned route checkpoints.
     * When deviation exceeds threshold (e.g. 2,000 meters), log deviation.
     */
    public function detectRouteDeviation(
        Trip $trip,
        VehicleLocation $location,
        int $thresholdMeters = 2000
    ): ?TripRouteDeviation {
        $checkpoints = $trip->checkpoints;
        if ($checkpoints->isEmpty()) {
            return null;
        }

        // Find distance to closest planned checkpoint or route segment
        $minDistance = PHP_INT_MAX;
        foreach ($checkpoints as $cp) {
            if ($cp->latitude && $cp->longitude) {
                $dist = $this->calculateHaversineDistance(
                    (float)$location->latitude,
                    (float)$location->longitude,
                    (float)$cp->latitude,
                    (float)$cp->longitude
                );
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                }
            }
        }

        if ($minDistance !== PHP_INT_MAX && $minDistance > $thresholdMeters) {
            return TripRouteDeviation::create([
                'company_id' => $trip->company_id,
                'trip_id' => $trip->id,
                'vehicle_id' => $location->vehicle_id,
                'deviation_point_lat' => $location->latitude,
                'deviation_point_lng' => $location->longitude,
                'deviation_distance_meters' => (int) round($minDistance),
                'deviation_started_at' => $location->recorded_at,
                'severity' => $minDistance > 10000 ? TripRouteDeviation::SEVERITY_HIGH : TripRouteDeviation::SEVERITY_MEDIUM,
                'notes' => "Vehicle drifted " . round($minDistance / 1000, 2) . " KM away from planned transit corridor.",
            ]);
        }

        return null;
    }

    /**
     * Stop detection: Identify stationary periods (speed < 2 km/h for > X minutes).
     */
    public function detectStops(int $vehicleId, ?int $tripId = null, int $minimumMinutes = 15): array
    {
        $query = VehicleLocation::withoutGlobalScopes()
            ->where('vehicle_id', $vehicleId);
        if ($tripId) {
            $query->where('trip_id', $tripId);
        }

        $locations = $query->orderBy('recorded_at')->get();
        $stops = [];
        $currentStopStart = null;
        $currentStopLoc = null;

        foreach ($locations as $loc) {
            $isStationary = ((float)$loc->speed_kmh < 2.0);

            if ($isStationary) {
                if (!$currentStopStart) {
                    $currentStopStart = $loc->recorded_at;
                    $currentStopLoc = $loc;
                }
            } else {
                if ($currentStopStart) {
                    $durationMins = $loc->recorded_at->diffInMinutes($currentStopStart);
                    if ($durationMins >= $minimumMinutes) {
                        $stops[] = [
                            'started_at' => $currentStopStart->toDateTimeString(),
                            'ended_at' => $loc->recorded_at->toDateTimeString(),
                            'duration_minutes' => $durationMins,
                            'latitude' => (float)$currentStopLoc->latitude,
                            'longitude' => (float)$currentStopLoc->longitude,
                        ];
                    }
                    $currentStopStart = null;
                    $currentStopLoc = null;
                }
            }
        }

        return $stops;
    }

    /**
     * Calculate dynamic Estimated Time of Arrival (ETA).
     */
    public function calculateEstimatedArrival(
        Trip $trip,
        float $currentLat,
        float $currentLng,
        ?float $currentSpeedKmh = null
    ): array {
        // Destination coordinates from final checkpoint or destination city hub
        $lastCheckpoint = $trip->checkpoints()->orderByDesc('stop_order')->first();
        $destLat = (float) ($lastCheckpoint?->latitude ?? 31.5204); // Default to Lahore coordinates if null
        $destLng = (float) ($lastCheckpoint?->longitude ?? 74.3587);

        $remainingDistanceMeters = $this->calculateHaversineDistance($currentLat, $currentLng, $destLat, $destLng);
        $remainingDistanceKm = round($remainingDistanceMeters / 1000, 2);

        // Calculate average transit speed (default 50 km/h for heavy cargo)
        $speed = ($currentSpeedKmh && $currentSpeedKmh > 10) ? $currentSpeedKmh : 50.0;
        $hoursNeeded = $remainingDistanceKm / $speed;
        $eta = now()->addMinutes((int) round($hoursNeeded * 60));

        return [
            'destination' => $trip->destination_city ?? 'Destination',
            'remaining_distance_km' => $remainingDistanceKm,
            'current_speed_kmh' => $speed,
            'estimated_hours_remaining' => round($hoursNeeded, 1),
            'estimated_arrival_time' => $eta->toDateTimeString(),
            'is_delayed' => $trip->estimated_arrival ? $eta->greaterThan($trip->estimated_arrival) : false,
        ];
    }

    /**
     * Standard Great Circle / Haversine Distance Formula (in meters).
     */
    public function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}

