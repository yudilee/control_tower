<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->string('doctype'); // Model name
            $table->string('field'); // Field name
            $table->boolean('can_read')->default(true);
            $table->boolean('can_write')->default(false);
            $table->timestamps();

            $table->unique(['role_id', 'doctype', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_permissions');
    }
};
