<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Preventive Maintenance Schedules & Service Interval Rules
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('title', 150);
            $table->enum('maintenance_category', [
                'oil_change',
                'service_interval',
                'brake_inspection',
                'tyre_rotation',
                'battery_check',
                'general_overhaul',
                'filter_replacement',
            ])->default('oil_change');

            $table->unsignedInteger('interval_km')->nullable();
            $table->unsignedInteger('interval_days')->nullable();

            $table->date('last_performed_at')->nullable();
            $table->unsignedBigInteger('last_performed_odometer_km')->nullable();

            $table->date('next_due_date')->nullable();
            $table->unsignedBigInteger('next_due_odometer_km')->nullable();

            $table->unsignedInteger('alert_lead_km')->default(500);
            $table->unsignedInteger('alert_lead_days')->default(7);

            $table->enum('status', ['active', 'due', 'overdue', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'vehicle_id']);
        });

        // 2. Workshop Job Cards
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('job_card_number', 50);

            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained('maintenance_schedules')->nullOnDelete();

            $table->enum('workshop_type', ['in_house', 'third_party'])->default('in_house');
            $table->string('workshop_name', 150)->nullable();
            $table->enum('service_type', [
                'preventive',
                'breakdown',
                'scheduled',
                'repair',
                'tyre_service',
                'oil_lube',
                'body_work',
            ])->default('preventive');

            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->unsignedBigInteger('odometer_km');

            $table->date('scheduled_date');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('downtime_hours', 8, 2)->default(0.00);

            $table->decimal('labour_charges', 12, 2)->default(0.00);
            $table->decimal('parts_total_cost', 12, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_cost', 12, 2)->default(0.00);

            $table->enum('payment_status', ['paid', 'unpaid', 'pending_approval'])->default('unpaid');
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');

            $table->text('issue_description')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->string('technician_name', 100)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'job_card_number']);
            $table->index(['company_id', 'vehicle_id', 'status']);
        });

        // 3. Spare Parts Usage per Job Card
        Schema::create('job_card_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('job_card_id')->constrained('job_cards')->onDelete('cascade');
            $table->string('part_name', 150);
            $table->string('part_number', 100)->nullable();
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->boolean('is_inventory_item')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'job_card_id']);
        });

        // 4. Vehicle Components Tracking (Tyres, Batteries, Oil, Filters)
        Schema::create('vehicle_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('job_card_id')->nullable()->constrained('job_cards')->nullOnDelete();

            $table->enum('component_type', [
                'tyre',
                'battery',
                'engine_oil',
                'filter',
                'brake_pad',
                'clutch',
                'other',
            ]);

            $table->string('brand_name', 100)->nullable();
            $table->string('model_or_size', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('position', 50)->nullable(); // e.g. front_left, rear_inner_right

            $table->date('installed_date');
            $table->unsignedBigInteger('installed_odometer_km');

            $table->unsignedInteger('expected_life_km')->nullable();
            $table->unsignedInteger('expected_life_days')->nullable();

            $table->date('removed_date')->nullable();
            $table->unsignedBigInteger('removed_odometer_km')->nullable();

            $table->enum('status', ['active', 'worn', 'replaced', 'scrapped'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_id', 'status']);
        });

        // 5. Fleet Maintenance & Anomaly Reminders / Alerts
        Schema::create('maintenance_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');

            $table->enum('alert_type', [
                'oil_change_due',
                'service_due',
                'insurance_expiry',
                'registration_expiry',
                'fitness_expiry',
                'suspicious_mileage',
                'fuel_anomaly',
                'component_life_warning',
            ]);

            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');

            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('due_odometer_km')->nullable();

            $table->boolean('is_resolved')->default(false);
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'is_resolved']);
            $table->index(['company_id', 'alert_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_alerts');
        Schema::dropIfExists('vehicle_components');
        Schema::dropIfExists('job_card_items');
        Schema::dropIfExists('job_cards');
        Schema::dropIfExists('maintenance_schedules');
    }
};

