<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make plate_number nullable for DMS imports
     * Many historical vehicles (pre-2000) don't have plate numbers recorded
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Drop unique constraint first
            $table->dropUnique('vehicles_plate_number_unique');
        });
        
        Schema::table('vehicles', function (Blueprint $table) {
            // Make nullable
            $table->string('plate_number')->nullable()->change();
            // Add regular index for searching
            $table->index('plate_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['plate_number']);
            $table->string('plate_number')->nullable(false)->change();
            $table->unique('plate_number');
        });
    }
};
