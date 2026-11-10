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
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropUnique('uniq_company_doc_year');
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->cascadeOnDelete();
            $table->unsignedSmallInteger('padding')->default(5)->after('prefix');
            $table->string('suffix', 10)->nullable()->after('padding');
            $table->string('reset_frequency', 20)->default('yearly')->after('suffix');

            $table->index(['company_id', 'branch_id', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['company_id', 'branch_id', 'document_type']);
            $table->dropColumn(['branch_id', 'padding', 'suffix', 'reset_frequency']);
        });
    }
};
