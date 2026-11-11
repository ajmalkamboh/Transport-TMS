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
        // 1. Journal Vouchers Header Table
        Schema::create('journal_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained('financial_years')->nullOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            
            $table->string('voucher_number', 50); // e.g. "JV-2026-00001", "CR-2026-00001", "BP-2026-00001"
            $table->date('voucher_date');
            
            $table->enum('voucher_type', [
                'journal',
                'cash_receipt',
                'cash_payment',
                'bank_receipt',
                'bank_payment',
                'contra',
                'adjustment',
                'opening',
            ])->default('journal');
            
            $table->decimal('total_debit', 14, 2)->default(0.00);
            $table->decimal('total_credit', 14, 2)->default(0.00);
            
            $table->enum('status', ['draft', 'posted', 'reversed', 'cancelled'])->default('draft');
            
            // Operational Source Links
            $table->string('source_module', 50)->nullable(); // customer_invoice, customer_payment, fuel_entry, trip_expense, job_card, owner_settlement, owner_payment, driver_settlement, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('reference_number', 100)->nullable();
            $table->text('narration');
            
            // Audit Users
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'voucher_number']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->index(['company_id', 'voucher_date']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'voucher_type']);
            $table->index(['company_id', 'source_module', 'source_id']);
        });

        // 2. Journal Voucher Line Items (The General Ledger detail lines)
        Schema::create('journal_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('journal_voucher_id')->constrained('journal_vouchers')->onDelete('cascade');
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->onDelete('cascade');
            
            $table->enum('entry_type', ['debit', 'credit']);
            $table->decimal('debit', 14, 2)->default(0.00);
            $table->decimal('credit', 14, 2)->default(0.00);
            $table->string('description', 255);
            
            // Sub-ledger dimension tagging for specialized ledgers
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('vehicle_owner_id')->nullable()->constrained('vehicle_owners')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('fuel_supplier_id')->nullable()->constrained('fuel_suppliers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            
            // Bank reconciliation tracking
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            
            $table->timestamps();

            $table->index(['company_id', 'chart_of_account_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'vehicle_owner_id']);
            $table->index(['company_id', 'driver_id']);
            $table->index(['company_id', 'fuel_supplier_id']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'vehicle_id']);
            $table->index(['company_id', 'reconciled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_voucher_items');
        Schema::dropIfExists('journal_vouchers');
    }
};

