<?php

namespace App\Services;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicationReview
{
    public function decide(Request $request, Publication $publication, bool $administrator): Publication
    {
        $data = $request->validate([
            'version' => 'required|integer|min:1',
            'decision' => 'required|in:published,review,rejected',
            'reason' => 'nullable|string|max:2000',
            'authorship' => $administrator ? 'nullable' : 'accepted',
            'confirmed_ids' => 'nullable|array|max:100',
            'confirmed_ids.*' => 'integer|distinct|exists:researchers,id',
            'researcher_id' => 'nullable|integer|exists:researchers,id',
            'title' => 'sometimes|required|string|max:2000',
            'year' => 'sometimes|nullable|integer|min:1800|max:'.(now()->year + 1),
            'venue' => 'sometimes|nullable|string|max:2000',
            'external_url' => 'sometimes|nullable|url:http,https|max:2048',
            'abstract' => 'sometimes|nullable|string|max:100000',
        ]);
        abort_unless($request->user()->active, 403);
        abort_unless($administrator || $data['decision'] === 'published', 403);
        if ($data['decision'] !== 'published') {
            $request->validate(['reason' => 'required|string|max:2000']);
        }

        return DB::transaction(function () use ($request, $publication, $administrator, $data) {
            $paper = Publication::lockForUpdate()->findOrFail($publication->id);
            abort_unless((int) $paper->revision === (int) $data['version'], 409, 'La ficha cambió. Recarga para revisar la versión actual.');
            $before = $paper->toArray();
            $profile = $request->user()->researcher;
            if (! $administrator) {
                abort_unless($profile && in_array($paper->status, ['review', 'incomplete', 'published'], true) && $paper->researchers()->whereKey($profile->id)->wherePivotIn('state', ['pending', 'confirmed'])->exists(), 403);
            }
            $paper->fill(collect($data)->only(['title', 'year', 'venue', 'external_url', 'abstract'])->all());
            if ($paper->isDirty('year')) {
                $metadata = $paper->metadata;
                $metadata['csl']['issued'] = ['date-parts' => [[(int) $paper->year]]];
                $paper->metadata = $metadata;
                $paper->published_date = null;
            }
            if ($data['decision'] === 'published') {
                abort_unless($paper->title && $paper->year && $paper->venue && $paper->authors && filter_var($paper->external_url, FILTER_VALIDATE_URL) && in_array(parse_url($paper->external_url, PHP_URL_SCHEME), ['http', 'https'], true), 422, 'Completa título, año, autores, revista y enlace antes de publicar.');
                $ids = $administrator ? ($data['confirmed_ids'] ?? []) : [$profile->id];
                if ($administrator && ! empty($data['researcher_id'])) {
                    $ids[] = $data['researcher_id'];
                }
                foreach (array_unique($ids) as $id) {
                    $previous = $paper->researchers()->whereKey($id)->first()?->pivot?->toArray();
                    $paper->researchers()->syncWithoutDetaching([$id => ['state' => 'confirmed', 'evidence' => $administrator ? 'admin_review' : 'researcher_declaration']]);
                    Audit::record('publication', $paper->id, 'attribution_review', $previous, ['researcher_id' => (int) $id, 'state' => 'confirmed'], $administrator ? 'Autoría confirmada durante revisión individual' : 'Declaración del titular del perfil');
                }
            }
            $paper->status = $data['decision'];
            $paper->revision++;
            $paper->user_modified_at = now();
            $paper->search_text = mb_strtolower($paper->title.' '.json_encode([$paper->authors, $paper->metadata['bibliografia']['palabras_clave'] ?? []], JSON_UNESCAPED_UNICODE));
            $paper->save();
            Audit::record('publication', $paper->id, $administrator ? 'admin_review' : 'authorship_confirmed', $before, $paper->toArray(), $data['reason'] ?? ($administrator ? 'Ficha revisada y publicada' : 'Publicación autorizada por el titular del perfil'));

            return $paper;
        });
    }
}
