<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Multiple Vehicle & Driver Assignments per Trip
        Schema::create('trip_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->enum('resource_type', ['vehicle', 'driver']);
            $table->enum('role', [
                'primary_vehicle',
                'trailer',
                'secondary_vehicle',
                'primary_driver',
                'co_driver',
            ]);

            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->boolean('override_allowed')->default(false);
            $table->string('override_reason')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'resource_type']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'vehicle_id', 'is_active']);
            $table->index(['company_id', 'driver_id', 'is_active']);
        });

        // 2. Route Checkpoints & Stops
        Schema::create('trip_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');

            $table->string('checkpoint_name', 100); // e.g. "Hyderabad Bypass Toll", "Sukkur Weighbridge"
            $table->unsignedSmallInteger('stop_order')->default(1);

            $table->dateTime('planned_arrival')->nullable();
            $table->dateTime('planned_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();

            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('delay_reason')->nullable();
            $table->unsignedBigInteger('odometer_km')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'passed', 'skipped'])->default('pending');
            $table->timestamps();

            $table->index(['trip_id', 'stop_order']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_checkpoints');
        Schema::dropIfExists('trip_resources');
    }
};

