<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\Researcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicationAuthors
{
    public function forEditor(Publication $publication, ?Researcher $self): array
    {
        return $this->includeSelf($publication->authors ?? [], $self);
    }

    public function read(Request $request, Publication $publication, ?Researcher $self): array
    {
        $data = $request->validate([
            'authors_editor' => 'nullable|in:1',
            'authors' => 'nullable|array|max:1000',
            'authors.*' => 'array',
            'authors.*.name' => 'required|string|max:500',
            'authors.*.researcher_id' => 'nullable|integer|exists:researchers,id',
            'authors.*.affiliations' => 'nullable|string|max:10000',
            'authors_text' => 'nullable|string|max:100000',
            'external_authors' => 'nullable|string|max:100000',
        ]);
        $previous = $publication->authors ?? [];
        $rows = $data['authors'] ?? [];
        if (! $request->hasAny(['authors_editor', 'authors', 'authors_text'])) {
            $rows = array_map(fn ($author) => ['name' => $author['nombre'], 'researcher_id' => $author['researcher_id'] ?? null], $previous);
        }
        if ($request->filled('authors_text') && ! $request->has('authors_editor')) {
            $rows = [];
            foreach (preg_split('/\R/u', $data['authors_text']) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line, 2));
                $row = ['name' => $parts[0]];
                if (isset($parts[1])) {
                    $row['affiliations'] = array_values(array_filter(array_map('trim', explode(';', $parts[1]))));
                }
                $rows[] = $row;
            }
        }
        foreach (preg_split('/[,\r\n]+/u', $data['external_authors'] ?? '') as $name) {
            if (trim($name) !== '') {
                $rows[] = ['name' => trim($name)];
            }
        }
        if (count($rows) > 1000) {
            throw ValidationException::withMessages(['authors' => 'Puedes registrar hasta 1000 autores.']);
        }
        $profiles = Researcher::whereIn('id', array_filter(array_column($rows, 'researcher_id')))->get()->keyBy('id');
        $authors = [];
        $seen = [];
        foreach ($rows as $row) {
            $profile = $profiles->get($row['researcher_id'] ?? null);
            $name = trim($profile?->name ?? $row['name']);
            if ($name === '' || mb_strlen($name) > 500) {
                throw ValidationException::withMessages(['authors' => 'Cada autor debe tener un nombre completo de hasta 500 caracteres.']);
            }
            $nameKey = $this->nameKey($name);
            $matches = collect($previous)->filter(fn ($author) => $profile && ! empty($author['researcher_id'])
                ? (int) $author['researcher_id'] === $profile->id
                : ($this->nameKey($author['nombre']) === $nameKey || ($profile?->orcid && ($author['orcid'] ?? null) === $profile->orcid)));
            $entry = $matches->count() === 1 ? $matches->first() : [];
            if ($profile && $entry && (! empty($entry['researcher_id']) || ($profile->orcid && ($entry['orcid'] ?? null) === $profile->orcid))) {
                $name = $entry['nombre'];
                $nameKey = $this->nameKey($name);
            }
            $key = $profile ? 'profile:'.$profile->id : 'name:'.$nameKey;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $seen['name:'.$nameKey] = true;
            $entry['nombre'] = $name;
            $affiliations = $row['affiliations'] ?? null;
            if (is_string($affiliations)) {
                $affiliations = $affiliations === implode('; ', $entry['afiliaciones'] ?? [])
                    ? ($entry['afiliaciones'] ?? [])
                    : array_values(array_filter(array_map('trim', explode(';', $affiliations))));
            }
            $entry['afiliaciones'] = $affiliations ?? $entry['afiliaciones'] ?? [];
            $entry['orcid'] = $entry['orcid'] ?? $profile?->orcid;
            if ($profile) {
                $entry['researcher_id'] = $profile->id;
            }
            $authors[] = $entry;
        }

        return $this->includeSelf($authors, $self);
    }

    public function linkSelected(Publication $publication): void
    {
        foreach (array_unique(array_filter(array_column($publication->authors, 'researcher_id'))) as $id) {
            if (! $publication->researchers()->whereKey($id)->exists()) {
                $publication->researchers()->attach($id, ['state' => 'pending', 'evidence' => 'author_selection']);
                Audit::record('publication', $publication->id, 'attribution_proposed', null, ['researcher_id' => (int) $id, 'state' => 'pending']);
            }
        }
    }

    private function includeSelf(array $authors, ?Researcher $self): array
    {
        if ($self) {
            $index = collect($authors)->search(fn ($author) => ! empty($author['researcher_id'])
                ? (int) $author['researcher_id'] === $self->id
                : (($self->orcid && ($author['orcid'] ?? null) === $self->orcid)
                    || $this->nameKey($author['nombre']) === $this->nameKey($self->name)));
            if ($index === false) {
                $authors[] = ['nombre' => $self->name, 'orcid' => $self->orcid, 'afiliaciones' => [], 'researcher_id' => $self->id];
            } else {
                $authors[$index]['researcher_id'] = $self->id;
            }
        }
        foreach ($authors as $index => &$author) {
            $author['posicion'] = $index + 1;
        }

        return $authors;
    }

    private function nameKey(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }
}
