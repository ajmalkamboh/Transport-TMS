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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 15, 2)->default(0.00);
            $table->decimal('quarterly_price', 15, 2)->default(0.00);
            $table->decimal('annual_price', 15, 2)->default(0.00);
            $table->unsignedInteger('trial_duration_days')->default(14);
            $table->integer('max_vehicles')->default(10); // -1 for unlimited
            $table->integer('max_users')->default(5); // -1 for unlimited
            $table->integer('max_branches')->default(2); // -1 for unlimited
            $table->integer('max_storage_mb')->default(5120); // 5GB, -1 for unlimited
            $table->json('features')->nullable(); // JSON list of enabled feature slugs
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
