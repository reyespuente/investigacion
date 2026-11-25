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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchProjectController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->routeIs('admin.*')) {
            Gate::authorize('create', ResearchProject::class);
        }
        $request->validate(['q' => 'nullable|string|max:200', 'line' => 'nullable|integer|exists:research_lines,id']);
        $query = ResearchProject::with(['researchLines', 'responsible'])->orderBy('name')->orderBy('id');
        if ($request->filled('q')) {
            $pattern = '%'.$request->input('q').'%';
            $query->where(fn ($query) => $query->whereLike('name', $pattern)
                ->orWhereLike('description', $pattern)
                ->orWhereLike('responsible_name', $pattern)
                ->orWhereHas('responsible', fn ($person) => $person->whereLike('name', $pattern))
                ->orWhereLike('department', $pattern)
                ->orWhereLike('funding_institution', $pattern)
                ->orWhereLike('funding_call', $pattern));
        }
        if ($request->filled('line')) {
            $query->whereHas('researchLines', fn ($query) => $query->whereKey($request->integer('line')));
        }

        return view('projects.index', ['projects' => $query->paginate(12)->withQueryString(), 'lines' => ResearchLine::orderBy('name')->get()]);
    }

    public function show(ResearchProject $project): View
    {
        return view('projects.show', ['project' => $project->load(['researchLines', 'responsible']), 'participants' => $project->participants()]);
    }

    public function create(): View
    {
        Gate::authorize('create', ResearchProject::class);

        return $this->form(new ResearchProject, true);
    }

    public function edit(Request $request, ResearchProject $project): View
    {
        $managingTeam = $request->routeIs('admin.*');
        $this->authorizeSave($project, $managingTeam);

        return $this->form($project, $managingTeam);
    }

    private function form(ResearchProject $project, bool $managingTeam): View
    {
        return view('projects.edit', [
            'project' => $project->load(['researchLines', 'responsible']),
            'participants' => $project->participants(),
            'lines' => ResearchLine::orderBy('program')->orderBy('name')->get(),
            'researchers' => $managingTeam ? Researcher::orderBy('name')->get(['id', 'name', 'department']) : collect(),
            'managingTeam' => $managingTeam,
        ]);
    }

    public function save(Request $request, ?ResearchProject $project = null): RedirectResponse
    {
        $managingTeam = $request->routeIs('admin.*');
        $this->authorizeSave($project, $managingTeam);
        $fields = [
            'name' => 'required|string|max:500',
            'description' => 'nullable|string|max:20000',
            'department' => 'nullable|string|max:250',
            'funding_call' => 'nullable|string|max:250',
            'funding_institution' => 'nullable|string|max:250',
            'period_start' => 'nullable|date_format:Y,Y-m-d',
            'period_end' => 'nullable|date_format:Y,Y-m-d',
        ];
        $rules = $fields + [
            'version' => $project ? 'required|integer|min:1' : 'nullable|integer',
            'research_lines_present' => 'nullable|in:1',
            'research_lines' => 'nullable|array|max:50',
            'research_lines.*' => 'integer|distinct|exists:research_lines,id',
        ];
        $data = $request->validate($managingTeam ? $rules + [
            'responsible_name' => 'required_without:responsible_researcher_id|nullable|string|max:500',
            'responsible_researcher_id' => 'nullable|integer|exists:researchers,id',
            'participants_present' => 'nullable|in:1',
            'participants' => 'nullable|array|max:4',
            'participants.*' => 'array:name,researcher_id',
            'participants.*.name' => 'required|string|max:500',
            'participants.*.researcher_id' => 'nullable|integer|exists:researchers,id',
            'external_participants' => 'nullable|string|max:3000',
        ] : $rules);

        $saved = DB::transaction(function () use ($data, $fields, $project, $managingTeam) {
            $record = $project ? ResearchProject::lockForUpdate()->findOrFail($project->id) : new ResearchProject;
            if ($project) {
                $this->authorizeSave($record, $managingTeam);
                abort_unless((int) $record->revision === (int) $data['version'], 409, 'El proyecto cambió. Recarga antes de guardar.');
            }
            $before = $record->exists ? $this->snapshot($record) : null;
            $details = array_intersect_key($data, $fields);
            if (! $record->exists || array_key_exists('description', $data)) {
                $details['description'] = $data['description'] ?? '';
            }
            if ($managingTeam) {
                $responsible = isset($data['responsible_researcher_id']) ? Researcher::findOrFail($data['responsible_researcher_id']) : null;
                $details['responsible_name'] = $responsible?->name ?? $data['responsible_name'];
                $details['responsible_researcher_id'] = $responsible?->id;
                $replacingParticipants = ! $record->exists || (bool) array_intersect_key($data, ['participants_present' => true, 'participants' => true, 'external_participants' => true]);
                $team = $replacingParticipants ? $data : ['participants' => $record->participants()
                    ->map(fn ($person) => ['name' => $person->name, 'researcher_id' => $person->researcher_id])->all()];
                $participants = $this->participants($team, $responsible, $details['responsible_name']);
            }
            $record->fill($details + ['revision' => ($record->revision ?? 0) + 1])->save();
            if ($managingTeam && $replacingParticipants) {
                DB::table('project_participants')->where('research_project_id', $record->id)->delete();
                foreach ($participants as $index => $participant) {
                    DB::table('project_participants')->insert($participant + ['research_project_id' => $record->id, 'position' => $index + 1]);
                }
            }
            if (isset($data['research_lines_present']) || array_key_exists('research_lines', $data)) {
                $record->researchLines()->sync($data['research_lines'] ?? []);
            }
            Audit::record('research_project', $record->id, $before ? 'updated' : 'created', $before, $this->snapshot($record));

            return $record;
        });

        return redirect()->route('projects.show', $saved)->with('success', 'Proyecto guardado.');
    }

    public function destroy(Request $request, ResearchProject $project): RedirectResponse
    {
        Gate::authorize('delete', $project);
        $data = $request->validate(['confirm_delete' => 'accepted', 'version' => 'required|integer|min:1']);

        DB::transaction(function () use ($project, $data) {
            $record = ResearchProject::lockForUpdate()->findOrFail($project->id);
            abort_unless((int) $record->revision === (int) $data['version'], 409, 'El proyecto cambió. Recarga antes de eliminarlo.');
            $before = $this->snapshot($record);
            $record->delete();
            Audit::record('research_project', $record->id, 'deleted', $before);
        });

        return redirect()->route('admin.projects')->with('success', 'Proyecto eliminado.');
    }

    private function authorizeSave(?ResearchProject $project, bool $managingTeam): void
    {
        if ($managingTeam || ! $project) {
            Gate::authorize('create', ResearchProject::class);
        } else {
            Gate::authorize('update', $project);
        }
    }

    /** @return list<array{name: string, researcher_id: int|null}> */
    private function participants(array $data, ?Researcher $responsible, string $responsibleName): array
    {
        $participants = $data['participants'] ?? [];
        foreach (preg_split('/[,\r\n]+/u', $data['external_participants'] ?? '') as $name) {
            if (trim($name) !== '') {
                $participants[] = ['name' => trim($name), 'researcher_id' => null];
            }
        }
        if (count($participants) > 4) {
            throw ValidationException::withMessages(['participants' => 'Cada proyecto admite hasta cuatro participantes además del responsable.']);
        }
        $profiles = Researcher::whereIn('id', array_filter(array_column($participants, 'researcher_id')))->get()->keyBy('id');
        $seen = [];
        foreach ($participants as &$participant) {
            $profile = $profiles->get($participant['researcher_id'] ?? null);
            $participant = ['name' => trim($profile?->name ?? $participant['name']), 'researcher_id' => $profile?->id];
            $nameKey = Str::lower(Str::squish($participant['name']));
            $key = $profile ? 'profile:'.$profile->id : 'external:'.$nameKey;
            if (($profile && $responsible && $profile->id === $responsible->id)
                || ((! $profile || ! $responsible) && $nameKey === Str::lower(Str::squish($responsibleName)))) {
                throw ValidationException::withMessages(['participants' => 'El responsable no debe repetirse como participante.']);
            }
            if (mb_strlen($participant['name']) > 500 || $participant['name'] === '' || isset($seen[$key])) {
                throw ValidationException::withMessages(['participants' => 'Revisa los participantes. Cada persona debe aparecer una vez y tener un nombre de hasta 500 caracteres.']);
            }
            $seen[$key] = true;
        }

        return array_values($participants);
    }

    /** @return array{project: array, participants: array, lines: list<int>} */
    private function snapshot(ResearchProject $project): array
    {
        return [
            'project' => $project->toArray(),
            'participants' => $project->participants()->toArray(),
            'lines' => $project->researchLines()->pluck('research_lines.id')->all(),
        ];
    }
}
