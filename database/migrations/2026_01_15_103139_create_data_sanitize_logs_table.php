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
        Schema::create('data_sanitize_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g., 'customer_address', 'customer_name'
            $table->integer('records_affected');
            $table->json('details')->nullable(); // Store sample of changes
            $table->string('run_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sanitize_logs');
    }
};
