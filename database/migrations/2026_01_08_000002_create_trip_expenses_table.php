<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('voucher_number', 50);
            $table->date('expense_date');

            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

            $table->enum('expense_category', [
                'toll_tax',
                'driver_allowance',
                'food',
                'accommodation',
                'parking',
                'loading_unloading',
                'route_expense',
                'emergency_repair',
                'fuel_surcharge',
                'police_fine',
                'weight_bridge',
                'other',
            ]);

            $table->decimal('amount', 12, 2);
            $table->enum('payment_method', [
                'cash',
                'driver_advance',
                'company_card',
                'bank_transfer',
                'credit',
            ])->default('cash');

            $table->string('supporting_document_path')->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'posted',
            ])->default('draft');

            $table->string('rejection_reason')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'voucher_number']);
            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_expenses');
    }
};

