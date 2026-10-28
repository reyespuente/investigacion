<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->longText('author_search')->nullable();
            $table->longText('tag_search')->nullable();
        });
        DB::table('publications')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $authors = json_decode($row->authors, true) ?? [];
                $metadata = json_decode($row->metadata, true) ?? [];
                DB::table('publications')->where('id', $row->id)->update(['author_search' => mb_strtolower(implode(' ', array_column($authors, 'nombre'))), 'tag_search' => mb_strtolower(implode(' ', $metadata['bibliografia']['palabras_clave'] ?? []))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn(['author_search', 'tag_search']));
    }
};
