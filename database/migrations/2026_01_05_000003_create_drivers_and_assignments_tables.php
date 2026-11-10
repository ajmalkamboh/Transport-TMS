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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('current_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('name', 191);
            $table->string('father_name', 191)->nullable();
            $table->string('cnic_number', 30);
            $table->string('phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->string('license_number', 50);
            $table->string('license_category', 50)->default('HTV'); // HTV, LTV, PSV
            $table->date('license_expiry_date');
            $table->string('license_issuing_authority', 100)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->enum('employment_type', ['permanent', 'contractual', 'trip_based', 'daily_wages'])->default('permanent');
            $table->decimal('basic_salary', 10, 2)->default(0.00);
            $table->decimal('per_trip_allowance', 10, 2)->default(0.00);
            $table->decimal('daily_batta', 10, 2)->default(0.00);
            $table->enum('status', ['available', 'on_trip', 'on_leave', 'suspended', 'terminated'])->default('available');
            $table->decimal('total_advances_balance', 12, 2)->default(0.00);
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'cnic_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'license_expiry_date']);
        });

        Schema::create('vehicle_driver_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->boolean('is_primary')->default(true); // Primary driver vs Co-driver
            $table->dateTime('assigned_at');
            $table->dateTime('unassigned_at')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->foreignId('assigned_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_id', 'status']);
            $table->index(['company_id', 'driver_id', 'status']);
        });

        Schema::create('driver_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('advance_number', 50);
            $table->date('date');
            $table->decimal('amount', 10, 2);
            $table->string('purpose', 191); // Fuel advance, En-route food, Emergency
            $table->string('payment_method', 30)->default('cash');
            $table->string('reference', 100)->nullable();
            $table->enum('status', ['disbursed', 'settled', 'partially_settled', 'cancelled'])->default('disbursed');
            $table->decimal('settled_amount', 10, 2)->default(0.00);
            $table->decimal('balance_amount', 10, 2)->default(0.00);
            $table->unsignedBigInteger('trip_id')->nullable();
            $table->foreignId('disbursed_by')->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'driver_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_advances');
        Schema::dropIfExists('vehicle_driver_assignments');
        Schema::dropIfExists('drivers');
    }
};

