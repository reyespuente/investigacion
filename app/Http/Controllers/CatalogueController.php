<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Services\PublicationSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CatalogueController extends Controller
{
    public function home()
    {
        return view('home', ['latest' => Publication::published()->where('year', '<=', now()->year)->orderByDesc('year')->orderByDesc('published_date')->orderByDesc('id')->take(4)->get(), 'ranking' => $this->ranking()->take(5)->get(), 'count' => Publication::published()->count(), 'people' => Researcher::whereHas('publications', fn ($q) => $q->published()->where('publication_researcher.state', 'confirmed'))->count(), 'abstracts' => Publication::published()->whereNotNull('abstract')->count(), 'years' => $this->years()]);
    }

    private function years()
    {
        return Publication::published()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year');
    }

    private function ranking()
    {
        return Researcher::withCount(['publications as publications_count' => fn ($q) => $q->published()->where('publication_researcher.state', 'confirmed')])->whereHas('publications', fn ($q) => $q->published()->where('publication_researcher.state', 'confirmed'))->orderByDesc('publications_count')->orderBy('name');
    }

    public function index(Request $r, PublicationSearch $search)
    {
        $publications = $search->query($r)->paginate(12)->withQueryString();
        if ($r->expectsJson()) {
            return response()->json(['html' => view('partials.results', compact('publications'))->render(), 'total' => $publications->total()]);
        }

return view('publications.index', compact('publications') + ['years' => $this->years()]);
    }

    public function show(Publication $publication)
    {
        abort_unless($publication->status === 'published' || (auth()->check() && Gate::allows('update', $publication)), 404);
        $publication->load(['researchers' => fn ($q) => $q->wherePivot('state', 'confirmed'), 'documents' => fn ($q) => $q->where('is_public', true)]);

        return view('publications.show', compact('publication'));
    }

    public function researchers(Request $r)
    {
        $r->validate(['q' => 'nullable|string|max:150']);
        $q = Researcher::withCount(['publications as publications_count' => fn ($q) => $q->published()->where('publication_researcher.state', 'confirmed')]);
        if ($r->filled('q')) {
            $q->where('name', 'like', '%'.$r->q.'%');
        }$researchers = $q->orderBy('name')->paginate(24)->withQueryString();

        return view('researchers.index', compact('researchers'));
    }

    public function researcher(Researcher $researcher)
    {
        $publications = $researcher->publications()->published()->wherePivot('state', 'confirmed')->orderByDesc('year')->paginate(10);
        $documents = $researcher->documents()->whereNull('publication_id')->where('is_public', true)->get();

        return view('researchers.show', compact('researcher', 'publications', 'documents'));
    }

    public function statistics()
    {
        return view('statistics', ['ranking' => $this->ranking()->take(20)->get(), 'types' => Publication::published()->selectRaw('type, COUNT(*) as total')->groupBy('type')->orderByDesc('total')->get(), 'years' => Publication::published()->selectRaw('year, COUNT(*) as total')->whereNotNull('year')->groupBy('year')->orderByDesc('year')->get()]);
    }
}
