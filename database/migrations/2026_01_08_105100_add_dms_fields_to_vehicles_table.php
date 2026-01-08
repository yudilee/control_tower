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
        Schema::table('vehicles', function (Blueprint $table) {
            // DMS identifiers
            $table->string('dms_magic')->nullable()->unique()->after('id');
            $table->string('customer_dms_magic')->nullable()->after('dms_magic');
            
            // Vehicle details
            $table->string('franchise')->nullable()->after('model');
            $table->string('variant')->nullable()->after('franchise');
            $table->string('description')->nullable()->after('variant');
            $table->string('mhl_number')->nullable()->after('vin');
            $table->string('engine_number')->nullable()->after('mhl_number');
            
            // Dates
            $table->date('registration_date')->nullable();
            $table->date('last_service_date')->nullable();
            
            // DMS metadata
            $table->timestamp('dms_imported_at')->nullable();
            
            // Indexes
            $table->index('dms_magic');
            $table->index('customer_dms_magic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['dms_magic']);
            $table->dropIndex(['customer_dms_magic']);
            $table->dropColumn([
                'dms_magic', 'customer_dms_magic',
                'franchise', 'variant', 'description',
                'mhl_number', 'engine_number',
                'registration_date', 'last_service_date',
                'dms_imported_at',
            ]);
        });
    }
};
