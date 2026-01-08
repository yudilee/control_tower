<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates customer_aliases table for handling name variations from invoices/accounting
     */
    public function up(): void
    {
        Schema::create('customer_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('alias_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Unique constraint on alias_name to prevent duplicates
            $table->unique('alias_name');
            
            // Index for fast lookups
            $table->index('alias_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_aliases');
    }
};
