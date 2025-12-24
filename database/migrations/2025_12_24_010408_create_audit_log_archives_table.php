<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create archive table with same structure as audit_logs
        Schema::create('audit_log_archives', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('original_created_at')->nullable();
            $table->timestamps();
            
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('original_created_at');
        });

        // Add archive settings to backup_schedules table
        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->boolean('audit_archive_enabled')->default(true);
            $table->integer('audit_archive_days')->default(90); // Archive logs older than 90 days
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_archives');
        
        Schema::table('backup_schedules', function (Blueprint $table) {
            $table->dropColumn(['audit_archive_enabled', 'audit_archive_days']);
        });
    }
};
