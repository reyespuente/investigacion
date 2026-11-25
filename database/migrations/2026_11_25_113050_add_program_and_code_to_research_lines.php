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
        Schema::table('research_lines', function (Blueprint $table) {
            $table->string('program', 250)->default('');
            $table->string('code', 64)->nullable()->unique();
            $table->unique(['program', 'name']);
            $table->dropUnique(['name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('research_lines', function (Blueprint $table) {
            $table->unique('name');
            $table->dropUnique(['program', 'name']);
            $table->dropUnique(['code']);
            $table->dropColumn(['program', 'code']);
        });
    }
};
