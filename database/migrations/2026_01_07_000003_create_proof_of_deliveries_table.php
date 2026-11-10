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
        Schema::create('proof_of_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('consignment_id')->constrained('consignments')->onDelete('cascade');
            $table->foreignId('trip_consignment_id')->nullable()->constrained('trip_consignments')->nullOnDelete();

            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 30)->nullable();
            $table->string('receiver_cnic', 30)->nullable();

            $table->dateTime('delivery_datetime');
            $table->unsignedInteger('delivered_quantity');
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('short_quantity')->default(0);

            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();

            $table->string('signature_file_path')->nullable();
            $table->string('document_file_path')->nullable();
            $table->string('photo_file_path')->nullable();

            $table->enum('status', [
                'verified',
                'pending_verification',
                'disputed',
            ])->default('verified');

            $table->text('exception_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'trip_id']);
            $table->index(['company_id', 'consignment_id']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proof_of_deliveries');
    }
};
