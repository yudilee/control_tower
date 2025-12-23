<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_settings', function (Blueprint $table) {
            $table->id();
            $table->string('command')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('schedule')->default('daily'); // daily, weekly, hourly, custom
            $table->string('time')->default('07:00');
            $table->tinyInteger('day_of_week')->nullable(); // 0=Sunday, 1=Monday, etc.
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->nullable(); // success, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_settings');
    }
};
