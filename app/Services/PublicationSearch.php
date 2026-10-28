<?php

namespace App\Services;

use App\Models\Publication;
use Illuminate\Http\Request;

class PublicationSearch
{
    public function query(Request $r)
    {
        $d = $r->validate(['q' => 'nullable|string|max:200', 'year' => 'nullable|integer|min:1800|max:2100', 'type' => 'nullable|string|max:100', 'abstract' => 'nullable|in:1', 'researcher' => 'nullable|integer', 'sort' => 'nullable|in:recent,oldest,title']);
        $q = Publication::published();
        if (! empty($d['q'])) {
            $q->where('search_text', 'like', '%'.mb_strtolower($d['q']).'%');
        }if (! empty($d['year'])) {
            $q->where('year', $d['year']);
        }if (! empty($d['type'])) {
            $q->where('type', $d['type']);
        }if (! empty($d['abstract'])) {
            $q->whereNotNull('abstract')->where('abstract', '<>', '');
        }if (! empty($d['researcher'])) {
            $q->whereHas('researchers', fn ($q) => $q->where('researchers.id', $d['researcher'])->where('publication_researcher.state', 'confirmed'));
        }

return match ($d['sort'] ?? 'recent') {
            'oldest' => $q->orderBy('year')->orderBy('id'),'title' => $q->orderBy('title')->orderBy('id'),default => $q->orderByDesc('year')->orderByDesc('published_date')->orderByDesc('id')
        };
    }
}
