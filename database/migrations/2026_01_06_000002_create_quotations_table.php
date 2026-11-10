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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('customer_contract_id')->nullable()->constrained('customer_contracts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('converted_booking_id')->nullable();

            $table->string('quotation_number', 50);
            $table->date('quotation_date');
            $table->date('valid_until');

            $table->string('origin_city', 100);
            $table->string('destination_city', 100);
            $table->text('origin_address')->nullable();
            $table->text('destination_address')->nullable();

            $table->string('cargo_description');
            $table->string('cargo_category', 50)->default('GENERAL_CARGO');
            $table->decimal('weight_tons', 8, 2)->default(0.00);
            $table->decimal('volume_cbm', 8, 2)->nullable();
            $table->unsignedInteger('package_count')->default(1);

            $table->string('required_vehicle_category', 50)->default('HCV');
            $table->string('required_vehicle_type', 50)->default('TRAILER_22W');

            $table->enum('rate_type', [
                'fixed',
                'per_ton',
                'per_km',
                'per_vehicle',
                'per_quantity',
                'route_based',
                'negotiated',
            ])->default('fixed');

            $table->decimal('base_rate', 12, 2)->default(0.00);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('freight_amount', 12, 2)->default(0.00);

            $table->decimal('loading_charges', 10, 2)->default(0.00);
            $table->decimal('unloading_charges', 10, 2)->default(0.00);
            $table->decimal('toll_charges', 10, 2)->default(0.00);
            $table->decimal('detention_charges', 10, 2)->default(0.00);
            $table->decimal('other_charges', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('tax_percentage', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('net_total_amount', 12, 2)->default(0.00);

            $table->enum('status', [
                'draft',
                'sent',
                'approved',
                'rejected',
                'expired',
                'converted',
            ])->default('draft');

            $table->timestamp('approved_at')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'quotation_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
