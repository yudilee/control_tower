<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove unique constraint from name column since we now group by customer_id
     * and multiple DMS customers can have same name
     */
    public function up(): void
    {
        Schema::table('customer_summaries', function (Blueprint $table) {
            $table->dropUnique('customer_summaries_name_unique');
            $table->index('name'); // Keep index for searching
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_summaries', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->unique('name');
        });
    }
};
