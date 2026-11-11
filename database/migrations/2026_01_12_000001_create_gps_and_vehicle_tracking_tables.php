<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. GPS Devices
        Schema::create('gps_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            
            $table->string('device_identifier', 100); // Unique hardware ID / serial
            $table->string('imei', 30)->nullable();
            $table->string('sim_card_number', 30)->nullable();
            $table->string('protocol_provider', 50)->default('generic'); // traccar, teltonika, generic
            $table->string('model_name', 100)->nullable();
            
            $table->enum('status', ['online', 'offline', 'dormant', 'faulty'])->default('offline');
            $table->dateTime('last_ping_at')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'device_identifier']);
            $table->index(['company_id', 'vehicle_id', 'is_active']);
        });

        // 2. Real Telemetry Vehicle Locations
        Schema::create('vehicle_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('gps_device_id')->nullable()->constrained('gps_devices')->nullOnDelete();
            
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('altitude_meters', 8, 2)->nullable();
            $table->decimal('speed_kmh', 6, 2)->default(0.00);
            $table->decimal('heading_degrees', 5, 2)->nullable(); // 0 to 360
            $table->boolean('ignition_status')->default(false);
            $table->unsignedSmallInteger('satellites_count')->default(0);
            $table->unsignedBigInteger('odometer_km')->nullable();
            $table->decimal('battery_level_percent', 5, 2)->nullable();
            
            $table->json('raw_payload')->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'recorded_at']);
            $table->index(['trip_id', 'recorded_at']);
        });

        // 3. Geofences
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            
            $table->string('name', 150);
            $table->enum('shape_type', ['circle', 'polygon'])->default('circle');
            $table->decimal('center_latitude', 10, 7)->nullable();
            $table->decimal('center_longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(500); // For circle shape
            $table->json('polygon_coordinates')->nullable(); // [[lat, lng], [lat, lng], ...]
            
            $table->boolean('alert_on_entry')->default(true);
            $table->boolean('alert_on_exit')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        // 4. Geofence Events
        Schema::create('geofence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('geofence_id')->constrained('geofences')->onDelete('cascade');
            
            $table->enum('event_type', ['entry', 'exit']);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['company_id', 'geofence_id', 'recorded_at']);
        });

        // 5. Trip Route Deviations
        Schema::create('trip_route_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            
            $table->decimal('deviation_point_lat', 10, 7);
            $table->decimal('deviation_point_lng', 10, 7);
            $table->unsignedInteger('deviation_distance_meters');
            $table->dateTime('deviation_started_at');
            $table->dateTime('deviation_ended_at')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'trip_id', 'deviation_started_at'], 'trd_company_trip_dev_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_route_deviations');
        Schema::dropIfExists('geofence_events');
        Schema::dropIfExists('geofences');
        Schema::dropIfExists('vehicle_locations');
        Schema::dropIfExists('gps_devices');
    }
};

