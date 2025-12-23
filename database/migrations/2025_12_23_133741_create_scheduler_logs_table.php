<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->enum('status', ['success', 'failed', 'running']);
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('triggered_by')->default('scheduler'); // scheduler, manual
            $table->timestamps();
            
            $table->index('command');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
