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
        // 1. Customers Table (Individual & Corporate)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('customer_code', 50)->nullable();
            $table->enum('customer_type', ['corporate', 'individual'])->default('corporate');
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('designation')->nullable();
            $table->string('phone', 30);
            $table->string('mobile', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('tax_ntn', 50)->nullable();
            $table->string('tax_strn', 50)->nullable();
            $table->boolean('is_tax_exempt')->default(false);
            $table->text('billing_address')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->unsignedInteger('credit_days')->default(0);
            $table->enum('payment_terms', ['to_pay', 'paid', 'credit_15_days', 'credit_30_days', 'credit_60_days', 'bill_to_customer'])->default('credit_30_days');
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('current_balance', 12, 2)->default(0.00);
            $table->enum('status', ['active', 'inactive', 'blacklisted'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_type']);
            $table->index(['company_id', 'customer_code']);
            $table->index(['company_id', 'phone']);
        });

        // 2. Customer Delivery Addresses (Multiple Shipping Points)
        Schema::create('customer_delivery_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('location_title'); // e.g. "Lahore Central Depot", "Port Qasim Berth # 4"
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->text('address');
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'customer_id']);
        });

        // 3. Customer Commercial Contracts & Agreed Tariffs
        Schema::create('customer_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('contract_number', 50);
            $table->string('contract_title');
            $table->enum('rate_type', ['fixed', 'per_ton', 'per_km', 'per_vehicle', 'per_quantity', 'route_based', 'negotiated'])->default('route_based');
            $table->decimal('base_rate', 12, 2)->default(0.00);
            $table->string('payment_terms', 50)->default('credit_30_days');
            $table->unsignedInteger('credit_days')->default(30);
            $table->decimal('credit_limit', 12, 2)->default(0.00);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('tax_percentage', 5, 2)->default(0.00);
            $table->decimal('loading_charges', 10, 2)->default(0.00);
            $table->decimal('unloading_charges', 10, 2)->default(0.00);
            $table->decimal('detention_rate_per_day', 10, 2)->default(0.00);
            $table->decimal('demurrage_rate_per_day', 10, 2)->default(0.00);
            $table->json('route_rates')->nullable(); // Corridor rates [{"origin": "Karachi", "destination": "Lahore", "rate": 95000}]
            $table->json('cargo_rates')->nullable(); // Cargo-specific rates [{"category": "Perishable", "unit": "ton", "rate": 3500}]
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('active');
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'contract_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_contracts');
        Schema::dropIfExists('customer_delivery_addresses');
        Schema::dropIfExists('customers');
    }
};
