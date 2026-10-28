<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PublicationEditorController extends Controller
{
    public function create(Request $r)
    {
        abort_unless($r->user()->researcher || $r->user()->role === 'admin', 403);

        return view('account.publication', ['publication' => new Publication(['type' => 'article', 'authors' => [], 'metadata' => []])]);
    }

    public function edit(Publication $publication)
    {
        Gate::authorize('update', $publication);

        return view('account.publication', compact('publication'));
    }

    public function save(Request $r, ?Publication $publication = null)
    {
        if ($publication) {
            Gate::authorize('update', $publication);
        } else {
            abort_unless($r->user()->researcher || $r->user()->role === 'admin', 403);
        }
        if ($r->filled('doi')) {
            $r->merge(['doi' => mb_strtolower(trim($r->input('doi')))]);
        }
        $d = $r->validate(['title' => 'required|string|max:2000', 'year' => 'required|integer|min:1800|max:'.(now()->year + 1), 'published_date' => 'nullable|date_format:Y-m-d', 'type' => ['required', Rule::in(array_keys(config('catalogue.types')))], 'venue' => 'required|string|max:2000', 'doi' => ['nullable', 'string', 'max:255', 'regex:~^10\.\d{4,9}/\S+$~i', Rule::unique('publications')->ignore($publication?->id)], 'external_url' => 'required|url:http,https|max:2048', 'abstract' => 'nullable|string|max:100000', 'authors_text' => 'required|string|max:100000', 'volume' => 'nullable|string|max:100', 'issue' => 'nullable|string|max:100', 'pages' => 'nullable|string|max:100', 'version' => 'nullable|integer']);
        if (! empty($d['published_date'])) {
            abort_unless((int) substr($d['published_date'], 0, 4) === (int) $d['year'], 422, 'El año y la fecha deben coincidir.');
        }
        $authors = [];
        foreach (preg_split('/\R/u', $d['authors_text']) as $line) {
            if (! trim($line)) {
                continue;
            }$parts = array_map('trim', explode('|', $line));
            abort_if(count($authors) >= 1000 || mb_strlen($parts[0]) > 500, 422);
            $previous = collect($publication?->authors ?? [])->filter(fn ($a) => mb_strtolower(trim($a['nombre'])) === mb_strtolower($parts[0]));
            $entry = $previous->count() === 1 ? $previous->first() : [];
            $authors[] = array_replace($entry, ['posicion' => count($authors) + 1, 'nombre' => $parts[0], 'orcid' => $entry['orcid'] ?? null, 'afiliaciones' => ! empty($parts[1]) ? [$parts[1]] : []]);
        }abort_unless($authors, 422);
        $p = DB::transaction(function () use ($d, $authors, $publication, $r) {
            $p = $publication ? Publication::lockForUpdate()->findOrFail($publication->id) : new Publication;
            if ($publication) {
                abort_unless((int) $d['version'] === (int) $p->revision, 409, 'Otra persona actualizó esta ficha. Recarga la página para revisar los cambios.');
            }
            $before = $p->exists ? $p->toArray() : null;
            $meta = $p->metadata ?? [];
            $meta['bibliografia'] = array_replace($meta['bibliografia'] ?? [], ['volumen' => $d['volume'] ?? null, 'numero' => $d['issue'] ?? null, 'paginas' => $d['pages'] ?? null]);
            $meta['csl'] = array_replace($meta['csl'] ?? [], ['volume' => $d['volume'] ?? null, 'issue' => $d['issue'] ?? null, 'page' => $d['pages'] ?? null, 'issued' => ['date-parts' => [! empty($d['published_date']) ? array_map('intval', explode('-', $d['published_date'])) : [(int) $d['year']]]]]);
            $p->fill(collect($d)->only(['title', 'year', 'published_date', 'type', 'venue', 'doi', 'external_url', 'abstract'])->all());
            $p->doi = $p->doi ? mb_strtolower($p->doi) : null;
            $p->authors = $authors;
            $p->metadata = $meta;
            $p->search_text = mb_strtolower($p->title.' '.json_encode($authors, JSON_UNESCAPED_UNICODE));
            $p->status = 'published';
            $p->origin = $r->user()->role === 'admin' ? 'administrator' : 'researcher';
            $p->user_modified_at = now();
            $p->revision = ($p->revision ?? 0) + 1;
            if (! $p->exists) {
                $p->submitted_by = $r->user()->id;
                $p->origin = 'researcher';
            }$p->save();
            if ($r->user()->researcher) {
                $p->researchers()->syncWithoutDetaching([$r->user()->researcher->id => ['state' => 'confirmed', 'evidence' => 'researcher_declaration']]);
            }Audit::record('publication', $p->id, $before ? 'updated' : 'created', $before, $p->toArray());

            return $p;
        });

        return redirect()->route('publications.show',$p)->with('success','Publicación guardada. Tus aportaciones se publican directamente y quedan registradas en el historial.');
    }
}
