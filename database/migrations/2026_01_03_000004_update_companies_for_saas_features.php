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
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('status')->constrained('subscription_plans')->onDelete('set null');
            $table->foreignId('owner_id')->nullable()->after('plan_id')->constrained('users')->onDelete('set null');
            $table->json('feature_overrides')->nullable()->after('settings');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['plan_id', 'owner_id', 'feature_overrides', 'trial_ends_at']);
        });
    }
};
