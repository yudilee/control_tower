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
        Schema::create('duplicate_customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_hash', 64)->unique(); // Hash of sorted names for uniqueness
            $table->json('names'); // Array of customer names in this group
            $table->json('entries'); // Detailed entries with job_count, vehicle_count, source
            $table->string('classification'); // DMS_ISSUE or USER_MISTAKE
            $table->integer('dms_count')->default(0);
            $table->integer('user_count')->default(0);
            $table->enum('status', ['pending', 'merged', 'dismissed'])->default('pending');
            $table->timestamps();
            
            $table->index('status');
            $table->index('classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_customer_groups');
    }
};
