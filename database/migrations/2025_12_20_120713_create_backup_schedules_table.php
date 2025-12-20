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
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('frequency')->default('daily'); // daily, weekly, monthly
            $table->string('time')->default('00:00'); // HH:MM format
            $table->integer('day_of_week')->nullable(); // 0=Sunday, 1=Monday, etc. (for weekly)
            $table->integer('day_of_month')->nullable(); // 1-31 (for monthly)
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_schedules');
    }
};
