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
            $table->string('trading_name')->nullable()->after('name');
            $table->string('registration_number', 50)->nullable()->after('tax_number');
            $table->string('logo_path')->nullable()->after('registration_number');
            $table->string('website')->nullable()->after('email');
            $table->string('country', 50)->default('Pakistan')->after('address');
            $table->string('currency', 10)->default('PKR')->after('country');
            $table->string('timezone', 50)->default('Asia/Karachi')->after('currency');
            $table->unsignedTinyInteger('currency_precision')->default(2)->after('timezone');
            $table->boolean('is_tax_enabled')->default(true)->after('currency_precision');
            $table->string('tax_name', 50)->default('Sales Tax / GST')->after('is_tax_enabled');
            $table->decimal('default_tax_rate', 5, 2)->default(16.00)->after('tax_name');
            $table->timestamp('onboarding_completed_at')->nullable()->after('settings');
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('onboarding_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'trading_name',
                'registration_number',
                'logo_path',
                'website',
                'country',
                'currency',
                'timezone',
                'currency_precision',
                'is_tax_enabled',
                'tax_name',
                'default_tax_rate',
                'onboarding_completed_at',
                'onboarding_step',
            ]);
        });
    }
};

