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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_owner_id')->nullable()->constrained('vehicle_owners')->nullOnDelete();
            $table->foreignId('active_contract_id')->nullable()->constrained('owner_contracts')->nullOnDelete();
            $table->string('registration_number', 30);
            $table->string('fleet_number', 30)->nullable();
            $table->enum('ownership_type', ['company_owned', 'market_attached', 'rented'])->default('company_owned');
            $table->string('vehicle_category', 50)->default('HCV');
            $table->string('vehicle_type', 50)->default('TRAILER_22W');
            $table->string('make', 50)->nullable();
            $table->string('model', 50)->nullable();
            $table->unsignedSmallInteger('manufacturing_year')->nullable();
            $table->string('chassis_number', 50)->nullable();
            $table->string('engine_number', 50)->nullable();
            $table->decimal('payload_capacity_tons', 8, 2)->default(0.00);
            $table->decimal('volume_capacity_cbm', 8, 2)->nullable();
            $table->string('fuel_type', 30)->default('DIESEL');
            $table->decimal('fuel_tank_capacity_liters', 8, 2)->nullable();
            $table->unsignedBigInteger('current_odometer_km')->default(0);
            $table->date('registration_expiry_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->date('fitness_expiry_date')->nullable();
            $table->date('route_permit_expiry_date')->nullable();
            $table->enum('status', ['available', 'on_trip', 'maintenance', 'inactive', 'retired'])->default('available');
            $table->unsignedBigInteger('current_driver_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'registration_number']);
            $table->index(['company_id', 'ownership_type', 'status']);
            $table->index(['company_id', 'branch_id']);
            $table->index(['company_id', 'fitness_expiry_date']);
            $table->index(['company_id', 'insurance_expiry_date']);
            $table->index(['company_id', 'route_permit_expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

