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
        Schema::create('fleet_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('documentable_type', 100); // Vehicle, Driver, VehicleOwner
            $table->unsignedBigInteger('documentable_id');
            $table->string('document_type', 50); // registration, insurance, fitness, route_permit, driving_license, cnic
            $table->string('document_number', 100)->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedInteger('file_size_kb')->default(0);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedSmallInteger('alert_before_days')->default(15);
            $table->boolean('is_verified')->default(true);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'documentable_type', 'documentable_id'], 'idx_fleet_doc_entity');
            $table->index(['company_id', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_documents');
    }
};

