<?php

namespace App\Http\Controllers;

use App\Models\Researcher;
use App\Models\ResearchLine;
use App\Models\ResearchProject;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResearchProjectController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()?->role === 'admin', 403);
        }
        $request->validate(['q' => 'nullable|string|max:200', 'line' => 'nullable|integer|exists:research_lines,id']);
        $query = ResearchProject::with('researchLines')->orderByDesc('updated_at')->orderByDesc('id');
        if ($request->filled('q')) {
            $query->where(fn ($query) => $query->whereLike('name', '%'.$request->q.'%')->orWhereLike('description', '%'.$request->q.'%'));
        }
        if ($request->filled('line')) {
            $query->whereHas('researchLines', fn ($query) => $query->whereKey($request->integer('line')));
        }

        return view('projects.index', ['projects' => $query->paginate(12)->withQueryString(), 'lines' => ResearchLine::orderBy('name')->get()]);
    }

    public function show(ResearchProject $project): View
    {
        return view('projects.show', ['project' => $project->load('researchLines'), 'participants' => $project->participants()]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('projects.edit', ['project' => new ResearchProject, 'participants' => collect(), 'lines' => ResearchLine::orderBy('name')->get()]);
    }

    public function edit(ResearchProject $project): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('projects.edit', ['project' => $project->load('researchLines'), 'participants' => $project->participants(), 'lines' => ResearchLine::orderBy('name')->get()]);
    }

    public function save(Request $request, ?ResearchProject $project = null): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'name' => 'required|string|max:500',
            'description' => 'required|string|max:20000',
            'version' => $project ? 'required|integer|min:1' : 'nullable|integer',
            'participants' => 'nullable|array|max:5',
            'participants.*.name' => 'required|string|max:500',
            'participants.*.researcher_id' => 'nullable|integer|exists:researchers,id',
            'external_participants' => 'nullable|string|max:3000',
            'research_lines' => 'nullable|array|max:50',
            'research_lines.*' => 'integer|distinct|exists:research_lines,id',
        ]);
        $participants = $data['participants'] ?? [];
        foreach (preg_split('/[,\r\n]+/u', $data['external_participants'] ?? '') as $name) {
            if (trim($name) !== '') {
                $participants[] = ['name' => trim($name), 'researcher_id' => null];
            }
        }
        if (count($participants) > 5) {
            throw ValidationException::withMessages(['participants' => 'Cada proyecto puede tener como máximo 5 participantes, incluyendo los externos.']);
        }
        $profiles = Researcher::whereIn('id', array_filter(array_column($participants, 'researcher_id')))->get()->keyBy('id');
        $seen = [];
        foreach ($participants as &$participant) {
            $profile = $profiles->get($participant['researcher_id'] ?? null);
            $participant = ['name' => trim($profile?->name ?? $participant['name']), 'researcher_id' => $profile?->id];
            $key = $profile ? 'profile:'.$profile->id : 'external:'.mb_strtolower($participant['name']);
            if (mb_strlen($participant['name']) > 500 || $participant['name'] === '' || isset($seen[$key])) {
                throw ValidationException::withMessages(['participants' => 'Revisa los participantes. Cada persona debe aparecer una vez y tener un nombre de hasta 500 caracteres.']);
            }
            $seen[$key] = true;
        }
        unset($participant);
        $saved = DB::transaction(function () use ($data, $participants, $project) {
            $record = $project ? ResearchProject::lockForUpdate()->findOrFail($project->id) : new ResearchProject;
            if ($project) {
                abort_unless((int) $record->revision === (int) $data['version'], 409, 'El proyecto cambió. Recarga antes de guardar.');
            }
            $before = $record->exists ? ['project' => $record->toArray(), 'participants' => $record->participants()->toArray(), 'lines' => $record->researchLines()->pluck('research_lines.id')->all()] : null;
            $record->fill(['name' => $data['name'], 'description' => $data['description'], 'revision' => ($record->revision ?? 0) + 1])->save();
            DB::table('project_participants')->where('research_project_id', $record->id)->delete();
            foreach ($participants as $index => $participant) {
                DB::table('project_participants')->insert($participant + ['research_project_id' => $record->id, 'position' => $index + 1]);
            }
            $record->researchLines()->sync($data['research_lines'] ?? []);
            Audit::record('research_project', $record->id, $before ? 'updated' : 'created', $before, ['project' => $record->toArray(), 'participants' => $participants, 'lines' => $data['research_lines'] ?? []]);

            return $record;
        });

        return redirect()->route('projects.show', $saved)->with('success', 'Proyecto guardado.');
    }
}
