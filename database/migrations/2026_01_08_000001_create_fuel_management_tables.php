<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fuel Suppliers (PSO, Shell, Total, Attock, Local Bunkers)
        Schema::create('fuel_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('contact_person', 100)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('ntn_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('balance', 12, 2)->default(0.00);
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        // 2. Fuel Stations / Pump Outlets
        Schema::create('fuel_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('fuel_supplier_id')->nullable()->constrained('fuel_suppliers')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['company_id', 'city']);
        });

        // 3. Fuel Cards (Corporate fleet cards linked to supplier/pump)
        Schema::create('fuel_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('fuel_supplier_id')->nullable()->constrained('fuel_suppliers')->nullOnDelete();
            $table->string('card_number', 50);
            $table->foreignId('assigned_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->decimal('monthly_limit', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00);
            $table->date('expiry_date')->nullable();
            $table->enum('status', ['active', 'blocked', 'expired'])->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'card_number']);
            $table->index(['company_id', 'status']);
        });

        // 4. Fuel Entries & Refuel Consumption Logs
        Schema::create('fuel_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('entry_number', 50);
            $table->date('entry_date');

            $table->foreignId('fuel_supplier_id')->nullable()->constrained('fuel_suppliers')->nullOnDelete();
            $table->foreignId('fuel_station_id')->nullable()->constrained('fuel_stations')->nullOnDelete();
            $table->foreignId('fuel_card_id')->nullable()->constrained('fuel_cards')->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();

            $table->string('fuel_type', 30)->default('diesel'); // diesel, petrol, cng, adblue
            $table->decimal('quantity_liters', 10, 2);
            $table->decimal('rate_per_liter', 10, 2);
            $table->decimal('total_amount', 12, 2);

            $table->enum('payment_method', ['fuel_card', 'cash', 'credit', 'driver_advance', 'company_account'])->default('fuel_card');
            $table->enum('payment_status', ['paid', 'unpaid', 'settled'])->default('unpaid');

            // Odometer & Efficiency Calculations
            $table->unsignedBigInteger('odometer_km');
            $table->unsignedBigInteger('previous_odometer_km')->default(0);
            $table->unsignedBigInteger('distance_km')->default(0);
            $table->decimal('fuel_consumed_liters', 10, 2)->nullable();
            $table->decimal('fuel_economy_km_per_liter', 8, 2)->nullable();
            $table->decimal('cost_per_km', 10, 2)->nullable();
            $table->boolean('is_full_tank')->default(true);

            // Anomaly & Audit
            $table->boolean('is_suspicious')->default(false);
            $table->string('anomaly_reason')->nullable();
            $table->string('receipt_photo_path')->nullable();
            $table->string('invoice_reference', 100)->nullable();

            $table->enum('status', ['draft', 'verified', 'posted'])->default('verified');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'entry_number']);
            $table->index(['company_id', 'vehicle_id', 'entry_date']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'is_suspicious']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_entries');
        Schema::dropIfExists('fuel_cards');
        Schema::dropIfExists('fuel_stations');
        Schema::dropIfExists('fuel_suppliers');
    }
};
