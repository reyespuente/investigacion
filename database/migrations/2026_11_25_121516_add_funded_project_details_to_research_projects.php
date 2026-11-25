<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_projects', function (Blueprint $table) {
            $table->string('import_reference', 64)->nullable()->unique();
            $table->string('responsible_name', 500)->nullable();
            $table->foreignId('responsible_researcher_id')->nullable()->constrained('researchers')->nullOnDelete();
            $table->string('department', 250)->nullable();
            $table->string('funding_call', 250)->nullable();
            $table->string('funding_institution', 250)->nullable();
            $table->string('period_start', 10)->nullable();
            $table->string('period_end', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('research_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsible_researcher_id');
            $table->dropUnique(['import_reference']);
            $table->dropColumn(['import_reference', 'responsible_name', 'department', 'funding_call', 'funding_institution', 'period_start', 'period_end']);
        });
    }
};
