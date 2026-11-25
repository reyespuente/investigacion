<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Researcher;
use App\Models\ResearchProject;
use App\Services\PublicationSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CatalogueController extends Controller
{
    public function authorSuggestions(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'required|string|min:2|max:150']);
        $query = Researcher::query();
        foreach (array_slice(preg_split('/\s+/u', trim($data['q'])), 0, 10) as $word) {
            $query->whereLike('name', '%'.$word.'%');
        }

        return response()->json($query->orderBy('name')->limit(8)->get(['id', 'name', 'department', 'orcid']));
    }

    public function home()
    {
        return view('home', ['latest' => Publication::published()->where('year', '<=', now()->year)->orderByDesc('year')->orderByDesc('published_date')->orderByDesc('id')->take(4)->get(), 'ranking' => $this->ranking()->take(5)->get(), 'count' => Publication::published()->count(), 'people' => Researcher::whereHas('publications', fn ($q) => $q->published()->where('publication_researcher.state', 'confirmed'))->count(), 'years' => $this->years()]);
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

    public function researchers(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:150']);
        $r->validate(['department' => 'nullable|string|max:250']);
        $projectCount = ResearchProject::query()->selectRaw('count(*)')
            ->where(fn ($projects) => $projects->whereColumn('research_projects.responsible_researcher_id', 'researchers.id')
                ->orWhereExists(fn ($participants) => $participants->selectRaw('1')->from('project_participants')
                    ->whereColumn('project_participants.research_project_id', 'research_projects.id')
                    ->whereColumn('project_participants.researcher_id', 'researchers.id')));
        $q = Researcher::withCount(['publications' => fn ($publications) => $publications->published()->where('publication_researcher.state', 'confirmed')])
            ->selectSub($projectCount, 'projects_count');
        if ($r->filled('department')) {
            $q->where('department', $r->department);
        }
        if ($r->filled('q')) {
            $q->where('name', 'like', '%'.$r->q.'%');
        }
        $researchers = $q->orderByDesc('publications_count')->orderBy('name')->orderBy('id')->paginate(24)->withQueryString();

        return view('researchers.index', compact('researchers'));
    }

    public function researcher(Request $request, Researcher $researcher): View
    {
        $section = $request->query('seccion', $request->query->has('page') ? 'publicaciones' : 'informacion');
        if (! in_array($section, ['informacion', 'publicaciones', 'proyectos'], true)) {
            $section = 'informacion';
        }
        if ($section === 'informacion') {
            $researcher->load(['researchLines', 'user']);
        }

        $content = match ($section) {
            'publicaciones' => ['publications' => $researcher->publications()->published()
                ->wherePivot('state', 'confirmed')->orderByDesc('year')->orderByDesc('publications.id')
                ->paginate(10)->appends(['seccion' => 'publicaciones'])],
            'proyectos' => $this->researcherProjects($researcher),
            default => ['documents' => $researcher->documents()->whereNull('publication_id')->where('is_public', true)->get()],
        };

        return view('researchers.show', $content + compact('researcher', 'section'));
    }

    /** @return array{responsibleProjects: Collection, participatingProjects: Collection} */
    private function researcherProjects(Researcher $researcher): array
    {
        $projects = ResearchProject::with('responsible')
            ->where('responsible_researcher_id', $researcher->id)
            ->orWhereIn('id', $researcher->projects()->select('research_projects.id'))
            ->orderBy('name')->get();
        [$responsibleProjects, $participatingProjects] = $projects->partition(
            fn (ResearchProject $project) => (int) $project->responsible_researcher_id === $researcher->id,
        );

        return compact('responsibleProjects', 'participatingProjects');
    }

    public function statistics(Request $r, PublicationSearch $search)
    {
        $query = $search->query($r)->reorder();
        $total = (clone $query)->count();
        $withDoi = (clone $query)->whereNotNull('doi')->where('doi', '<>', '')->count();
        $withAbstract = (clone $query)->whereNotNull('abstract')->where('abstract', '<>', '')->count();
        $ranking = Researcher::withCount(['publications as publications_count' => fn ($q) => $q->whereIn('publications.id', (clone $query)->select('publications.id'))->where('publication_researcher.state', 'confirmed')])->whereHas('publications', fn ($q) => $q->whereIn('publications.id', (clone $query)->select('publications.id'))->where('publication_researcher.state', 'confirmed'))->orderByDesc('publications_count')->orderBy('name')->take(10)->get();
        $data = [
            'total' => $total, 'withDoi' => $withDoi, 'withAbstract' => $withAbstract, 'ranking' => $ranking,
            'people' => Researcher::whereHas('publications', fn ($q) => $q->whereIn('publications.id', (clone $query)->select('publications.id'))->where('publication_researcher.state', 'confirmed'))->count(),
            'types' => (clone $query)->selectRaw('type,COUNT(*) as total')->groupBy('type')->orderByDesc('total')->get(),
            'years' => (clone $query)->selectRaw('year,COUNT(*) as total')->whereNotNull('year')->groupBy('year')->orderBy('year')->get(),
            'venues' => (clone $query)->selectRaw('venue,COUNT(*) as total')->whereNotNull('venue')->groupBy('venue')->orderByDesc('total')->take(8)->get(),
            'allYears' => $this->years(),
        ];

        return $r->expectsJson() ? response()->json(['html' => view('partials.statistics', $data)->render()]) : view('statistics', $data);
    }
}
