<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_profiles', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->text('configuration');
            $t->timestamps();
        });
        Schema::table('documents', fn (Blueprint $t) => $t->string('disk')->default('local'));
        Schema::table('researchers', fn (Blueprint $t) => $t->string('avatar_disk')->nullable());
        $disk = config('filesystems.default', 'local');
        if ($disk === 's3') {
            $id = (string) Str::uuid();
            DB::table('storage_profiles')->insert(['id' => $id, 'configuration' => Crypt::encryptString(json_encode(config('filesystems.disks.s3'), JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
            $disk = 'profile_'.$id;
            DB::table('site_settings')->updateOrInsert(['key' => 'runtime.storage_disk'], ['value' => json_encode($disk), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('documents')->update(['disk' => $disk]);
        DB::table('researchers')->whereNotNull('avatar_path')->update(['avatar_disk' => $disk]);
        foreach (['institution', 'network'] as $kind) {
            if (DB::table('site_settings')->where('key', $kind.'_logo_path')->exists()) {
                DB::table('site_settings')->insert(['key' => $kind.'_logo_disk', 'value' => $disk, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('documents', fn (Blueprint $t) => $t->dropColumn('disk'));
        Schema::table('researchers', fn (Blueprint $t) => $t->dropColumn('avatar_disk'));
        Schema::dropIfExists('storage_profiles');
    }
};
