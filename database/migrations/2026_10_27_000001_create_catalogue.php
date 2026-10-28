<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('google_id')->nullable()->unique();
            $t->string('role')->default('member');
            $t->boolean('must_change_password')->default(false);
            $t->boolean('active')->default(true);
        });
        Schema::create('researchers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->string('name', 500);
            $t->string('original_name', 500);
            $t->string('origin')->default('padron');
            $t->string('orcid')->nullable();
            $t->string('claim_email_hash', 64)->nullable()->unique();
            $t->text('bio')->nullable();
            $t->string('department')->nullable();
            $t->json('contacts')->nullable();
            $t->string('avatar_path')->nullable();
            $t->timestamps();
        });
        Schema::create('publications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('legacy_id')->nullable()->unique();
            $t->text('title');
            $t->unsignedSmallInteger('year')->nullable()->index();
            $t->string('published_date', 30)->nullable();
            $t->string('type', 100)->default('article')->index();
            $t->text('venue')->nullable();
            $t->string('doi')->nullable()->unique();
            $t->text('external_url')->nullable();
            $t->longText('abstract')->nullable();
            $t->json('authors');
            $t->json('metadata');
            $t->longText('search_text');
            $t->string('status')->default('review')->index();
            $t->string('origin')->default('catalogue');
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('user_modified_at')->nullable();
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
            $t->index(['status', 'year']);
        });
        Schema::create('publication_researcher', function (Blueprint $t) {
            $t->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $t->foreignId('researcher_id')->constrained()->cascadeOnDelete();
            $t->string('state')->default('pending');
            $t->string('evidence')->default('catalogue');
            $t->primary(['publication_id', 'researcher_id']);
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('researcher_id')->constrained()->cascadeOnDelete();
            $t->foreignId('publication_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('uploaded_by')->constrained('users');
            $t->string('title', 500);
            $t->string('kind');
            $t->string('path')->unique();
            $t->unsignedBigInteger('size');
            $t->string('license');
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->string('action');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_events', 'documents', 'publication_researcher', 'publications', 'researchers'] as $table) {
            Schema::dropIfExists($table);
        }Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['google_id', 'role', 'must_change_password', 'active']));
    }
};
