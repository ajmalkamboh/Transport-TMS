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
        // 1. Customer Invoices Table (Accounts Receivable)
        Schema::create('customer_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            
            $table->string('invoice_number', 50); // e.g. "INV-2026-00001"
            $table->date('invoice_date');
            $table->date('due_date');
            
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('tax_rate_percent', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('total_amount', 14, 2)->default(0.00);
            $table->decimal('paid_amount', 14, 2)->default(0.00);
            $table->decimal('balance_due', 14, 2)->default(0.00);
            
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'posted',
                'paid',
                'partially_paid',
                'cancelled',
                'reversed'
            ])->default('draft');
            
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            
            $table->foreignId('journal_voucher_id')->nullable()->constrained('journal_vouchers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'invoice_number']);
            $table->index(['company_id', 'customer_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->index(['company_id', 'due_date']);
        });

        // 2. Customer Invoice Line Items
        Schema::create('customer_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->onDelete('cascade');
            $table->foreignId('consignment_id')->nullable()->constrained('consignments')->nullOnDelete();
            
            $table->string('item_description', 255);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_rate', 12, 2)->default(0.00);
            $table->decimal('amount', 14, 2)->default(0.00);
            
            $table->timestamps();

            $table->index(['company_id', 'customer_invoice_id']);
        });

        // 3. Customer Payments & Advances Table
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('customer_invoice_id')->nullable()->constrained('customer_invoices')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            
            $table->string('receipt_number', 50); // e.g. "RCP-2026-00001"
            $table->date('receipt_date');
            $table->decimal('amount', 14, 2);
            
            // Advance treatment: advances remain in liability until applied to invoices
            $table->enum('payment_type', ['invoice_payment', 'customer_advance', 'on_account'])->default('invoice_payment');
            $table->decimal('unapplied_amount', 14, 2)->default(0.00);
            
            $table->enum('payment_method', ['cash', 'bank_transfer', 'cheque', 'online', 'other'])->default('bank_transfer');
            $table->string('reference_number', 100)->nullable();
            $table->string('cheque_number', 50)->nullable();
            $table->date('cheque_date')->nullable();
            $table->string('bank_name', 100)->nullable();
            
            $table->enum('status', ['draft', 'posted', 'cancelled', 'bounced'])->default('posted');
            $table->foreignId('journal_voucher_id')->nullable()->constrained('journal_vouchers')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'receipt_number']);
            $table->index(['company_id', 'customer_id', 'payment_type']);
            $table->index(['company_id', 'receipt_date']);
        });

        // 4. Customer Payment Allocations (Applying advances/receipts to invoices)
        Schema::create('customer_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('customer_payment_id')->constrained('customer_payments')->onDelete('cascade');
            $table->foreignId('customer_invoice_id')->constrained('customer_invoices')->onDelete('cascade');
            
            $table->decimal('allocated_amount', 14, 2);
            $table->date('allocated_at');
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();

            $table->index(['company_id', 'customer_payment_id']);
            $table->index(['company_id', 'customer_invoice_id']);
        });

        // 5. Driver Settlements (Trip Advances vs Allowances & Expenses)
        Schema::create('driver_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            
            $table->string('settlement_number', 50); // e.g. "DS-2026-00001"
            $table->date('settlement_date');
            
            $table->decimal('total_advances', 12, 2)->default(0.00); // Debited earlier to driver
            $table->decimal('driver_allowance', 12, 2)->default(0.00); // Earned trip salary/allowance
            $table->decimal('out_of_pocket_expenses', 12, 2)->default(0.00); // Expenses driver paid from pocket
            
            $table->decimal('net_payable', 12, 2)->default(0.00); // (allowance + out_of_pocket) - advances (if > 0)
            $table->decimal('net_recoverable', 12, 2)->default(0.00); // advances - (allowance + out_of_pocket) (if > 0)
            
            $table->enum('status', ['draft', 'approved', 'posted', 'settled'])->default('draft');
            $table->foreignId('journal_voucher_id')->nullable()->constrained('journal_vouchers')->nullOnDelete();
            
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'settlement_number']);
            $table->index(['company_id', 'driver_id', 'status']);
            $table->index(['company_id', 'trip_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_settlements');
        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('customer_invoice_items');
        Schema::dropIfExists('customer_invoices');
    }
};

