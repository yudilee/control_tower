<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove unique constraint from name column since we now group by customer_id
     * and multiple DMS customers can have same name
     */
    public function up(): void
    {
        // Drop the unique constraint - this converts it to a regular index
        Schema::table('customer_summaries', function (Blueprint $table) {
            $table->dropUnique('customer_summaries_name_unique');
        });
        
        // Only add index if it doesn't exist
        $indexExists = DB::select("SHOW INDEX FROM customer_summaries WHERE Key_name = 'customer_summaries_name_index'");
        if (empty($indexExists)) {
            Schema::table('customer_summaries', function (Blueprint $table) {
                $table->index('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_summaries', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = $sm->listTableIndexes('customer_summaries');
            if (array_key_exists('customer_summaries_name_index', $indexes)) {
                $table->dropIndex(['name']);
            }
            $table->unique('name');
        });
    }
};
