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
        // 1. Immutable Stock Ledger Entries
        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('transaction_type', 50); // opening_stock, grn, issue_maintenance, issue_general, transfer_in, transfer_out, purchase_return, adjustment_gain, adjustment_loss, damage, wastage, physical_count
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('quantity_in', 14, 2)->default(0);
            $table->decimal('quantity_out', 14, 2)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('total_cost', 16, 2);
            $table->decimal('balance_quantity', 14, 2);
            $table->decimal('balance_valuation', 16, 2);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'warehouse_id', 'inventory_item_id'], 'idx_stock_ledger_lookup');
            $table->index(['company_id', 'transaction_date'], 'idx_stock_ledger_date');
        });

        // 2. Stock Transfers (Inter-Warehouse)
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_number', 50);
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->date('transfer_date');
            $table->enum('status', ['draft', 'dispatched', 'completed', 'cancelled'])->default('draft');
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'transfer_number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity_transferred', 12, 2);
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('total_cost', 16, 2);
            $table->timestamps();
        });

        // 3. Stock Adjustments (Damage, Wastage, Physical Count, Gain/Loss)
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('adjustment_number', 50);
            $table->date('adjustment_date');
            $table->enum('adjustment_type', ['gain', 'loss', 'damage', 'wastage', 'physical_count']);
            $table->text('reason')->nullable();
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->foreignId('journal_voucher_id')->nullable()->constrained('journal_vouchers')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'adjustment_number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('system_quantity', 12, 2);
            $table->decimal('counted_quantity', 12, 2);
            $table->decimal('quantity_difference', 12, 2); // counted - system
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('total_difference_cost', 16, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_ledger_entries');
    }
};
