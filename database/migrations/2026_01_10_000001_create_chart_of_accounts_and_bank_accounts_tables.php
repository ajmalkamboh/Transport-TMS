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
        // 1. Chart of Accounts Table
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            
            $table->string('account_code', 30); // e.g. "1010", "1020", "2100", "4100", "5100"
            $table->string('account_name', 150);
            
            // Major 5 Account Categories
            $table->enum('account_type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            
            // Sub-group classification
            $table->string('account_group', 50); // current_asset, non_current_asset, current_liability, equity, operating_revenue, direct_operating_cost, admin_expense, other
            
            // Normal Balance convention
            $table->enum('normal_balance', ['debit', 'credit']);
            
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('current_balance', 14, 2)->default(0.00);
            
            $table->boolean('is_system')->default(false); // System accounts (AR, AP, Cash, Retained Earnings, Freight Revenue) cannot be deleted
            $table->boolean('is_reconcilable')->default(false); // Bank accounts eligible for bank reconciliation
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'account_code']);
            $table->index(['company_id', 'account_type']);
            $table->index(['company_id', 'account_group']);
            $table->index(['company_id', 'is_active']);
        });

        // 2. Bank & Cash Accounts Master Table
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->onDelete('cascade');
            
            $table->string('account_name', 150);
            $table->enum('account_type', ['bank', 'cash_in_hand', 'petty_cash'])->default('bank');
            $table->string('bank_name', 100)->nullable();
            $table->string('branch_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('currency', 10)->default('PKR');
            
            $table->decimal('opening_balance', 14, 2)->default(0.00);
            $table->decimal('current_balance', 14, 2)->default(0.00);
            
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'account_type']);
            $table->index(['company_id', 'is_active']);
        });

        // 3. Bank Reconciliations Table
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->onDelete('cascade');
            
            $table->date('statement_date');
            $table->decimal('statement_balance', 14, 2);
            $table->decimal('book_balance', 14, 2);
            $table->decimal('reconciled_balance', 14, 2);
            $table->decimal('difference', 14, 2)->default(0.00);
            
            $table->enum('status', ['draft', 'reconciled'])->default('draft');
            $table->text('notes')->nullable();
            
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id', 'statement_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
