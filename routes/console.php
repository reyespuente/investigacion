<?php

use App\Models\Researcher;
use App\Services\CatalogueImport;
use App\Services\RuntimeConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('catalog:import {file}', function () {
    app(RuntimeConfiguration::class)->apply();
    $this->info('Fichas procesadas: '.app(CatalogueImport::class)->import($this->argument('file')));
})->purpose('Importar el catálogo privado completo o repetir una importación sin borrar ediciones');
Artisan::command('catalog:claims {file}', function () {
    app(RuntimeConfiguration::class)->apply();
    $file = $this->argument('file');
    if (! is_file($file)) {
        throw new RuntimeException('No existe el archivo.');
    }$handle = fopen($file, 'rb');
    $headers = fgetcsv($handle, 0, ',', '"', '');
    if ($headers !== ['legacy_id', 'email']) {
        throw new RuntimeException('Se requiere el encabezado legacy_id,email.');
    }$rows = [];
    $emails = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        if (count($row) !== 2) {
            throw new RuntimeException('Fila inválida.');
        }[$id,$email] = $row;
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            continue;
        }if (! ctype_digit($id) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! in_array(explode('@', $email)[1], config('catalogue.domains'), true) || isset($emails[$email]) || ! Researcher::where('legacy_id', $id)->exists()) {
            throw new RuntimeException('El archivo contiene una identidad inválida o repetida.');
        }$emails[$email] = true;
        $rows[] = ['id' => (int) $id, 'hash' => Researcher::emailHash($email)];
    }fclose($handle);
    DB::transaction(function () use ($rows) {
        foreach ($rows as $r) {
            Researcher::where('legacy_id', $r['id'])->update(['claim_email_hash' => $r['hash']]);
        }
    });
    $this->info('Correos privados vinculados: '.count($rows).'. No se imprimen ni se guardan en texto en el padrón de la aplicación.');
})->purpose('Vincular correos privados mediante HMAC para reclamar perfiles');
Artisan::command('catalog:storage-check', function () {
    app(RuntimeConfiguration::class)->apply();
    $key = 'repository/connection-tests/'.Str::uuid().'.txt';
    $content = 'ITC storage connectivity check';
    try {
        if (! Storage::put($key, $content) || Storage::get($key) !== $content) {
            throw new RuntimeException('Verificación incompleta');
        }$this->info('Escritura y lectura correctas.');
    } catch (Throwable $e) {
        $this->error('No se pudo verificar el almacenamiento ('.class_basename($e).'). Revisa la configuración privada.');

        return 1;
    } finally {
        try {
            Storage::delete($key);
        } catch (Throwable) {
            $this->warn('No se pudo retirar el objeto temporal de comprobación.');
        }
    }

return 0;
})->purpose('Probar un objeto temporal exclusivo y retirarlo sin tocar otros archivos');

Artisan::command('catalog:researchers {file}', function () {
    $file = $this->argument('file');
    if (! is_file($file)) {
        throw new RuntimeException('No existe el archivo.');
    }$rows = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    foreach ($rows as $r) {
        if (! isset($r['legacy_id'],$r['name']) || ! is_int($r['legacy_id']) || ! is_string($r['name']) || strlen($r['name']) > 2000) {
            throw new RuntimeException('Identificador o nombre inválido.');
        }
    }
    DB::transaction(function () use ($rows) {
        foreach ($rows as $r) {
            Researcher::firstOrCreate(['legacy_id' => $r['legacy_id']], ['name' => $r['name'], 'original_name' => $r['name'], 'orcid' => $r['orcid'] ?? null, 'origin' => $r['origin'] ?? 'roster']);
        }
    });
    $this->info('Perfiles procesados: '.count($rows).'. No se modifican perfiles existentes.');
})->purpose('Importar los perfiles institucionales sin contraseñas ni correos públicos');
