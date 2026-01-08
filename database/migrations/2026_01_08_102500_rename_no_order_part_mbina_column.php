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
        // Only run if table exists and has old column name
        if (Schema::hasTable('part_orders') && Schema::hasColumn('part_orders', 'no_order_part_mbina')) {
            Schema::table('part_orders', function (Blueprint $table) {
                $table->renameColumn('no_order_part_mbina', 'no_order_part');
            });
        }
        
        // Update default status to 'pending' for existing records with 'ordered' status
        // This is optional - only if you want to reset existing data
        // DB::table('part_orders')->where('status', 'ordered')->update(['status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('part_orders') && Schema::hasColumn('part_orders', 'no_order_part')) {
            Schema::table('part_orders', function (Blueprint $table) {
                $table->renameColumn('no_order_part', 'no_order_part_mbina');
            });
        }
    }
};
