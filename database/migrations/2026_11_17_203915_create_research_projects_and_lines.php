<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('researchers', function (Blueprint $table) {
            $table->string('career', 250)->nullable();
            $table->string('professional_title', 250)->nullable();
            $table->json('degrees')->nullable();
            $table->json('recognitions')->nullable();
        });
        Schema::create('research_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 250)->unique();
            $table->timestamps();
        });
        Schema::create('research_line_researcher', function (Blueprint $table) {
            $table->foreignId('research_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('researcher_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_line_id', 'researcher_id']);
        });
        Schema::create('research_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 500);
            $table->longText('description');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
        Schema::create('project_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('researcher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 500);
            $table->unsignedTinyInteger('position');
            $table->unique(['research_project_id', 'position']);
        });
        Schema::create('research_line_research_project', function (Blueprint $table) {
            $table->foreignId('research_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_project_id')->constrained()->cascadeOnDelete();
            $table->primary(['research_line_id', 'research_project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_line_research_project');
        Schema::dropIfExists('project_participants');
        Schema::dropIfExists('research_projects');
        Schema::dropIfExists('research_line_researcher');
        Schema::dropIfExists('research_lines');
        Schema::table('researchers', function (Blueprint $table) {
            $table->dropColumn(['career', 'professional_title', 'degrees', 'recognitions']);
        });
    }
};
