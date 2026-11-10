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
        Schema::create('vehicle_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('cnic_number', 30)->nullable();
            $table->string('phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_title', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_iban', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            $table->enum('status', ['active', 'inactive', 'blacklisted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'cnic_number']);
        });

        Schema::create('owner_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('vehicle_owner_id')->constrained('vehicle_owners')->onDelete('cascade');
            $table->string('contract_number', 50);
            $table->enum('payment_model', [
                'fixed_per_trip',
                'percentage_of_freight',
                'per_km',
                'monthly_rental',
                'route_specific',
            ])->default('fixed_per_trip');
            $table->decimal('rate_amount', 12, 2)->default(0.00); // For fixed_per_trip, per_km, monthly_rental, route_specific
            $table->decimal('freight_percentage', 5, 2)->default(0.00); // e.g. 85.00% to owner
            $table->decimal('commission_percentage', 5, 2)->default(0.00); // e.g. 15.00% company cut
            $table->boolean('deduct_fuel')->default(true);
            $table->boolean('deduct_toll_taxes')->default(false);
            $table->boolean('deduct_advances')->default(true);
            $table->string('route_origin', 100)->nullable();
            $table->string('route_destination', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated'])->default('active');
            $table->text('terms_and_conditions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_owner_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_contracts');
        Schema::dropIfExists('vehicle_owners');
    }
};

