<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\Researcher;
use Illuminate\Support\Facades\DB;

class CatalogueImport
{
    public function import(string $path): int
    {
        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró el archivo de catálogo.');
        }
        $stream = str_ends_with($path, '.gz') ? gzopen($path, 'rb') : fopen($path, 'rb');
        $count = 0;
        try {
            while (($line = str_ends_with($path, '.gz') ? gzgets($stream, 10_000_000) : fgets($stream, 10_000_000)) !== false) {
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (! isset($row['publicacion_id'],$row['titulo'],$row['autores'])) {
                    throw new \RuntimeException('Ficha sin campos obligatorios.');
                }
                DB::transaction(function () use ($row) {
                    $p = Publication::firstOrNew(['legacy_id' => $row['publicacion_id']]);
                    if (! $p->user_modified_at) {
                        $p->fill(['title' => $row['titulo'], 'year' => $row['anio'], 'published_date' => $row['fecha'], 'type' => $row['tipo'] ?? 'other', 'venue' => $row['revista'], 'doi' => $row['doi'], 'external_url' => $row['enlace'], 'abstract' => $row['resumen_principal']['texto'] ?? null, 'authors' => $row['autores'], 'metadata' => $row, 'search_text' => mb_strtolower($row['titulo'].' '.json_encode([$row['autores'], $row['bibliografia']['palabras_clave'] ?? []], JSON_UNESCAPED_UNICODE)), 'status' => ($row['estado'] === 'lista' ? 'published' : ($row['estado'] === 'incompleta' ? 'incomplete' : 'review')), 'origin' => 'catalogue']);
                        $p->save();
                    }
                    $links = $row['researcher_links'] ?? array_map(fn ($v) => ['legacy_id' => $v['id'], 'state' => 'confirmed'], $row['personas_confirmadas'] ?? []);
                    foreach ($links as $link) {
                        $researcher = Researcher::where('legacy_id', $link['legacy_id'])->first();
                        if ($researcher && ! $p->researchers()->whereKey($researcher->id)->exists()) {
                            $p->researchers()->attach($researcher->id, ['state' => $link['state'], 'evidence' => 'catalogue']);
                        }
                    }
                });
                $count++;
            }
        } finally {
            str_ends_with($path, '.gz') ? gzclose($stream) : fclose($stream);
        }

return $count;
    }
}
