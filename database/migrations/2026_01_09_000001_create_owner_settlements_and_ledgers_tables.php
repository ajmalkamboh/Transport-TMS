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
        // 1. Owner Settlements Table
        Schema::create('owner_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_owner_id')->constrained('vehicle_owners')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('owner_contract_id')->nullable()->constrained('owner_contracts')->nullOnDelete();
            
            $table->string('settlement_number', 50);
            $table->date('settlement_date');
            
            // Commercial reference & calculation model
            $table->string('payment_model', 50)->default('fixed_per_trip');
            $table->decimal('customer_freight_total', 12, 2)->default(0.00); // Separate customer freight reference
            $table->decimal('agreed_owner_payable', 12, 2)->default(0.00); // Gross agreed payable before deductions
            
            // Deductions breakdown
            $table->decimal('fuel_deduction', 12, 2)->default(0.00);
            $table->decimal('repair_deduction', 12, 2)->default(0.00);
            $table->decimal('advance_deduction', 12, 2)->default(0.00);
            $table->decimal('penalty_deduction', 12, 2)->default(0.00);
            $table->decimal('other_deductions', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            
            // Additional charges / allowances (bonuses, demurrage, extra stop pay)
            $table->decimal('additional_charges', 12, 2)->default(0.00);
            
            // Final Payable & Balances
            $table->decimal('net_payable', 12, 2)->default(0.00); // agreed - total_deductions + additional_charges
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('balance_due', 12, 2)->default(0.00);
            
            // Immutability, snapshots and audit trail
            $table->json('contract_snapshot')->nullable(); // Snapshot of contract terms at calculation time
            $table->json('calculation_details')->nullable(); // Step-by-step formula and numbers used
            
            // Workflow Status
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'posted',
                'paid',
                'partially_paid',
                'reversed',
                'cancelled'
            ])->default('draft');
            
            $table->enum('payment_status', [
                'unpaid',
                'partially_paid',
                'paid'
            ])->default('unpaid');
            
            // Users and Approval stamps
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            
            // Reversal details (for immutable correction)
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_owner_id', 'status']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'settlement_number']);
        });

        // 2. Settlement Deduction Items Table (guarantees no double deduction)
        Schema::create('owner_settlement_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('owner_settlement_id')->constrained('owner_settlements')->onDelete('cascade');
            $table->enum('deduction_type', ['fuel', 'repair', 'advance', 'penalty', 'other']);
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->string('reference_type', 100)->nullable(); // e.g. App\Models\FuelEntry, App\Models\JobCard, App\Models\TripExpense
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255);
            $table->timestamps();

            $table->index(['company_id', 'reference_type', 'reference_id']);
            $table->index(['owner_settlement_id', 'deduction_type']);
        });

        // 3. Owner Payments Table
        Schema::create('owner_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_owner_id')->constrained('vehicle_owners')->onDelete('cascade');
            $table->foreignId('owner_settlement_id')->nullable()->constrained('owner_settlements')->nullOnDelete();
            
            $table->string('payment_number', 50);
            $table->date('payment_date');
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('bank_transfer');
            $table->string('payment_reference', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            
            $table->enum('status', ['completed', 'pending', 'cancelled', 'bounced'])->default('completed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'vehicle_owner_id', 'payment_date']);
            $table->index(['company_id', 'payment_number']);
        });

        // 4. Owner Ledgers Table (Complete running ledger)
        Schema::create('owner_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('vehicle_owner_id')->constrained('vehicle_owners')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            
            $table->date('transaction_date');
            $table->enum('transaction_type', [
                'opening_balance',
                'trip_payable',
                'deduction',
                'additional_charge',
                'advance',
                'payment',
                'adjustment_credit',
                'adjustment_debit',
                'reversal'
            ]);
            $table->enum('entry_type', ['credit', 'debit']); // credit = increases payable (company owes owner); debit = decreases payable (paid/deducted)
            $table->decimal('amount', 12, 2)->default(0.00);
            $table->decimal('running_balance', 12, 2)->default(0.00);
            
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->string('description', 255);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'vehicle_owner_id', 'transaction_date']);
            $table->index(['company_id', 'reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owner_ledgers');
        Schema::dropIfExists('owner_payments');
        Schema::dropIfExists('owner_settlement_deductions');
        Schema::dropIfExists('owner_settlements');
    }
};

