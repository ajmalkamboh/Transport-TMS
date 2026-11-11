<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Notification Logs
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->enum('channel', ['in_app', 'email', 'sms', 'whatsapp']);
            $table->string('event_type', 100); // trip_dispatch, delivery_delayed, pod_completed, document_expiry, maintenance_due, etc.
            $table->string('recipient', 150); // email address, phone number, or user identifier
            $table->string('title', 200);
            $table->text('body');
            $table->json('payload')->nullable();
            
            $table->enum('status', ['pending', 'sent', 'failed'])->default('sent');
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            
            $table->timestamps();

            $table->index(['company_id', 'event_type', 'status']);
            $table->index(['company_id', 'user_id', 'created_at']);
        });

        // 2. Notification Settings per Tenant
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('event_type', 100);
            
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);
            $table->json('alert_recipients')->nullable(); // Default emails / phone numbers for tenant alerts
            
            $table->timestamps();

            $table->unique(['company_id', 'event_type']);
        });

        // 3. Database & System Backups Registry
        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete(); // Null for platform-wide backup
            
            $table->string('filename', 255);
            $table->string('disk', 50)->default('local');
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->string('checksum_sha256', 64);
            
            $table->enum('backup_type', ['database', 'storage', 'full'])->default('database');
            $table->enum('status', ['completed', 'verified', 'failed'])->default('completed');
            $table->dateTime('verified_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index(['backup_type', 'status']);
        });

        // 4. Update Users table for Customer, Vehicle Owner & Driver Portal Access
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('branch_id')->constrained('customers')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'vehicle_owner_id')) {
                $table->foreignId('vehicle_owner_id')->nullable()->after('customer_id')->constrained('vehicle_owners')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'driver_id')) {
                $table->foreignId('driver_id')->nullable()->after('vehicle_owner_id')->constrained('drivers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'driver_id')) {
                $table->dropForeign(['driver_id']);
                $table->dropColumn('driver_id');
            }
            if (Schema::hasColumn('users', 'vehicle_owner_id')) {
                $table->dropForeign(['vehicle_owner_id']);
                $table->dropColumn('vehicle_owner_id');
            }
            if (Schema::hasColumn('users', 'customer_id')) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            }
        });

        Schema::dropIfExists('database_backups');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notification_logs');
    }
};

