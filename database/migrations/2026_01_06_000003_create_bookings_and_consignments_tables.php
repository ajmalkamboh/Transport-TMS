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
        // 1. Cargo Bookings Table
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('customer_contract_id')->nullable()->constrained('customer_contracts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('booking_number', 50);
            $table->date('booking_date');

            $table->string('origin_city', 100);
            $table->string('destination_city', 100);
            $table->text('origin_address')->nullable();
            $table->text('destination_address')->nullable();

            $table->date('expected_dispatch_date')->nullable();
            $table->date('expected_delivery_date')->nullable();

            $table->enum('payment_terms', ['to_pay', 'paid', 'credit', 'bill_to_company'])->default('to_pay');
            $table->decimal('total_freight', 12, 2)->default(0.00);
            $table->decimal('additional_charges', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('net_amount', 12, 2)->default(0.00);

            // Customer Advance Tracking (Segregated liability until invoiced)
            $table->decimal('advance_received', 12, 2)->default(0.00);
            $table->string('advance_payment_method', 50)->nullable();
            $table->string('advance_receipt_reference', 100)->nullable();
            $table->timestamp('advance_recorded_at')->nullable();
            $table->decimal('balance_amount', 12, 2)->default(0.00);

            $table->unsignedInteger('total_consignments_count')->default(0);
            $table->unsignedInteger('total_packages_count')->default(0);
            $table->decimal('total_weight_tons', 10, 2)->default(0.00);

            $table->text('special_instructions')->nullable();
            $table->enum('status', [
                'confirmed',
                'in_progress',
                'partially_dispatched',
                'completed',
                'cancelled',
            ])->default('confirmed');

            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'booking_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'booking_date']);
        });

        // 2. Consignments Table (Bilty / Lorry Receipt - CN)
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade'); // Consignor
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('consignment_number', 50); // e.g. LR-2026-00001
            $table->date('consignment_date');

            // Consignee / Receiver Information
            $table->string('consignee_name');
            $table->string('consignee_phone', 30);
            $table->string('consignee_cnic_or_ntn', 50)->nullable();
            $table->text('consignee_address')->nullable();
            $table->string('consignee_city', 100);

            $table->string('pickup_point')->nullable();
            $table->string('delivery_point')->nullable();

            // Cargo & Packaging
            $table->string('cargo_description');
            $table->string('cargo_category', 50)->default('GENERAL_CARGO');
            $table->enum('packaging_type', [
                'bags',
                'cartons',
                'pallets',
                'drums',
                'loose',
                'wooden_crates',
                'containers',
            ])->default('bags');

            $table->decimal('declared_value', 12, 2)->nullable();
            $table->decimal('gross_weight_kg', 10, 2)->default(0.00);
            $table->decimal('chargeable_weight_kg', 10, 2)->default(0.00);
            $table->decimal('volume_cbm', 8, 2)->nullable();

            // Split Quantity Tracking
            $table->unsignedInteger('total_quantity'); // Total units booked for this CN
            $table->unsignedInteger('dispatched_quantity')->default(0);
            $table->unsignedInteger('delivered_quantity')->default(0);
            $table->unsignedInteger('remaining_quantity'); // Total - dispatched

            // Pricing & Freight Terms
            $table->decimal('freight_rate', 10, 2)->default(0.00);
            $table->decimal('freight_charge', 12, 2)->default(0.00);
            $table->decimal('handling_charge', 10, 2)->default(0.00);
            $table->decimal('insurance_charge', 10, 2)->default(0.00);
            $table->decimal('total_charges', 12, 2)->default(0.00);
            $table->enum('payment_mode', ['to_pay', 'paid', 'credit'])->default('to_pay');

            $table->text('delivery_instructions')->nullable();
            $table->enum('status', [
                'booked',
                'at_origin_hub',
                'dispatched_in_transit',
                'at_destination_hub',
                'out_for_delivery',
                'delivered',
                'returned',
                'cancelled',
            ])->default('booked');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'consignment_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'booking_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'consignment_date']);
        });

        // 3. Consignment Item Packages Table
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('consignments')->onDelete('cascade');
            $table->string('item_description');
            $table->string('package_type', 50)->default('carton');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('weight_kg', 10, 2)->default(0.00);
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->string('dimensions', 50)->nullable(); // e.g. "10x15x20 cm"
            $table->timestamps();

            $table->index(['consignment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignments');
        Schema::dropIfExists('bookings');
    }
};

