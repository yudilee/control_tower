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
        Schema::table('users', function (Blueprint $table) {
            // 'local' for internal database, or ldap_server_id for LDAP users
            $table->string('auth_source')->default('local')->after('role');
            $table->index('auth_source');
        });

        // Set existing users to 'local' (they were created before this column)
        \DB::table('users')->whereNull('auth_source')->update(['auth_source' => 'local']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['auth_source']);
            $table->dropColumn('auth_source');
        });
    }
};
