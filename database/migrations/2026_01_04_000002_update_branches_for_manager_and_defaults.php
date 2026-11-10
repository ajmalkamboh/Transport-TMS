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
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('manager_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->boolean('is_default')->default(false)->after('is_head_office');
            $table->string('operating_hours', 100)->nullable()->after('address');
            $table->unsignedSmallInteger('dock_capacity')->default(2)->after('operating_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn(['manager_id', 'is_default', 'operating_hours', 'dock_capacity']);
        });
    }
};
