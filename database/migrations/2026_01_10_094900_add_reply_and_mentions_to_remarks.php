<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add reply and mention support to remarks table
     */
    public function up(): void
    {
        Schema::table('remarks', function (Blueprint $table) {
            // Parent comment for replies (nullable = top-level comment)
            $table->unsignedBigInteger('parent_id')->nullable()->after('job_id');
            $table->foreign('parent_id')->references('id')->on('remarks')->onDelete('cascade');
            
            // Array of mentioned user IDs
            $table->json('mentions')->nullable()->after('images');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('remarks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'mentions']);
        });
    }
};
