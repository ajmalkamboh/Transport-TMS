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
        // 1. Trips Management Table
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('trip_number', 50); // e.g. TR-2026-00001
            $table->date('trip_date');

            $table->string('origin_city', 100);
            $table->string('destination_city', 100);
            $table->text('planned_route')->nullable();

            $table->dateTime('estimated_departure')->nullable();
            $table->dateTime('estimated_arrival')->nullable();
            $table->dateTime('actual_departure')->nullable();
            $table->dateTime('actual_arrival')->nullable();

            $table->unsignedBigInteger('departure_odometer_km')->nullable();
            $table->unsignedBigInteger('arrival_odometer_km')->nullable();
            $table->unsignedBigInteger('total_distance_km')->nullable();

            $table->unsignedInteger('total_consignments_count')->default(0);
            $table->unsignedInteger('total_packages_count')->default(0);
            $table->decimal('total_weight_tons', 10, 2)->default(0.00);
            $table->decimal('total_freight_amount', 14, 2)->default(0.00);

            // Operational Lifecycle
            $table->enum('trip_status', [
                'planned',
                'loading',
                'dispatched',
                'in_transit',
                'delivered',
                'operationally_completed',
                'closed',
                'cancelled',
            ])->default('planned');

            // Financial Closure (Distinguished from operational completion)
            $table->enum('financial_status', [
                'pending',
                'partially_settled',
                'settled',
                'closed',
            ])->default('pending');

            // Loading details
            $table->dateTime('loading_started_at')->nullable();
            $table->dateTime('loading_completed_at')->nullable();
            $table->string('loading_supervisor', 100)->nullable();

            // Staff references
            $table->foreignId('assigned_dispatcher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('operationally_completed_at')->nullable();
            $table->dateTime('financially_closed_at')->nullable();

            $table->text('trip_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'trip_number']);
            $table->index(['company_id', 'trip_status']);
            $table->index(['company_id', 'financial_status']);
            $table->index(['company_id', 'trip_date']);
            $table->index(['company_id', 'branch_id']);
        });

        // 2. Multi-Consignment Allocations Table (Many-to-Many between Trips and Consignments)
        Schema::create('trip_consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('consignment_id')->constrained('consignments')->onDelete('cascade');

            $table->unsignedInteger('assigned_quantity'); // Packages loaded on this trip
            $table->decimal('weight_kg', 10, 2)->default(0.00);

            $table->enum('loading_status', [
                'planned',
                'loaded',
                'dispatched',
                'delivered',
                'rejected',
            ])->default('planned');

            // Delivery execution
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('short_quantity')->default(0);

            $table->enum('delivery_status', [
                'pending',
                'delivered_full',
                'delivered_partial',
                'failed',
                'rejected',
            ])->default('pending');

            $table->dateTime('delivery_time')->nullable();
            $table->string('receiver_name', 100)->nullable();
            $table->string('receiver_phone', 30)->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'consignment_id']);
            $table->index(['company_id', 'delivery_status']);
            $table->index(['company_id', 'loading_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_consignments');
        Schema::dropIfExists('trips');
    }
};

