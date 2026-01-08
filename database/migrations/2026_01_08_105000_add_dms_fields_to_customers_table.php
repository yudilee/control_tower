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
        Schema::table('customers', function (Blueprint $table) {
            // DMS identifier
            $table->string('dms_magic')->nullable()->unique()->after('id');
            
            // Address fields (1-5)
            $table->string('address_1')->nullable()->after('address');
            $table->string('address_2')->nullable()->after('address_1');
            $table->string('address_3')->nullable()->after('address_2');
            $table->string('address_4')->nullable()->after('address_3');
            $table->string('address_5')->nullable()->after('address_4');
            
            // Company info
            $table->string('company_name')->nullable()->after('name');
            $table->string('department')->nullable()->after('company_name');
            
            // Phone fields (from vehicle import)
            $table->string('phone_1')->nullable()->after('phone');
            $table->string('phone_2')->nullable()->after('phone_1');
            $table->string('phone_3')->nullable()->after('phone_2');
            $table->string('phone_4')->nullable()->after('phone_3');
            
            // DMS metadata
            $table->date('dms_created_at')->nullable();
            $table->timestamp('dms_imported_at')->nullable();
            
            // Index for lookups
            $table->index('dms_magic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['dms_magic']);
            $table->dropColumn([
                'dms_magic',
                'address_1', 'address_2', 'address_3', 'address_4', 'address_5',
                'company_name', 'department',
                'phone_1', 'phone_2', 'phone_3', 'phone_4',
                'dms_created_at', 'dms_imported_at',
            ]);
        });
    }
};
