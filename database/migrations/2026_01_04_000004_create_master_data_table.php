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
        Schema::create('master_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
            $table->string('category', 50); // vehicle_category, fuel_type, freight_unit, etc.
            $table->string('code', 50);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('meta')->nullable(); // custom attributes, symbols, default values
            $table->boolean('is_system')->default(false); // system-wide seed vs tenant custom
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'category', 'is_active']);
            $table->index(['category', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_data');
    }
};

