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
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // uninvoiced, performance, aging, parts_pending
            $table->string('schedule')->default('daily'); // daily, weekly, monthly
            $table->string('time')->default('08:00'); // Time to send
            $table->string('day_of_week')->nullable(); // For weekly: mon, tue, etc.
            $table->json('recipients'); // Array of email addresses
            $table->json('config')->nullable(); // Report-specific config (e.g., aging threshold)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
    }
};
