<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds customer_id foreign key to vehicles and jobs tables for proper CRM linking
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('customer_name')->constrained()->nullOnDelete();
            $table->index('customer_id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('customer_name')->constrained()->nullOnDelete();
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
