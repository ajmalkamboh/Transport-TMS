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
        Schema::table('job_card_items', function (Blueprint $table) {
            $table->foreignId('inventory_item_id')->nullable()->after('job_card_id')->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('inventory_item_id')->constrained('warehouses')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->after('is_inventory_item')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_card_items', function (Blueprint $table) {
            $table->dropForeign(['issued_by']);
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn(['inventory_item_id', 'warehouse_id', 'issued_by']);
        });
    }
};

