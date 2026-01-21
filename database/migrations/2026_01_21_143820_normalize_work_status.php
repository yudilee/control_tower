<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Normalize 'belum_diproses' to '1. Belum diproses (Tunggu Antrian)'
        DB::table('jobs')
            ->where('work_status', 'belum_diproses')
            ->update(['work_status' => '1. Belum diproses (Tunggu Antrian)']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to old format if needed
        DB::table('jobs')
            ->where('work_status', '1. Belum diproses (Tunggu Antrian)')
            ->update(['work_status' => 'belum_diproses']);
    }
};
