<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add unique index to RQ column (nullable values are allowed to be duplicate)
     */
    public function up(): void
    {
        Schema::table('part_orders', function (Blueprint $table) {
            // Make RQ unique - nullable values won't conflict
            $table->unique('rq', 'part_orders_rq_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('part_orders', function (Blueprint $table) {
            $table->dropUnique('part_orders_rq_unique');
        });
    }
};
