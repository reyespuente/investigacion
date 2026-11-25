<?php

namespace App\Http\Controllers;

use App\Models\ResearchLine;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResearchLineController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()?->role === 'admin', 403);
        }
        $data = $request->validate(['q' => 'nullable|string|max:200']);
        $query = ResearchLine::withCount(['researchers', 'projects'])->orderBy('program')->orderBy('name');
        if ($request->filled('q')) {
            $pattern = '%'.$data['q'].'%';
            $query->where(fn ($search) => $search->whereLike('name', $pattern)
                ->orWhereLike('program', $pattern)
                ->orWhereLike('code', $pattern));
        }

        return view('lines.index', ['lines' => $query->paginate(20)->withQueryString()]);
    }

    public function show(ResearchLine $line): View
    {
        return view('lines.show', [
            'line' => $line,
            'researchers' => $line->researchers()->withCount(['publications' => fn ($query) => $query->published()->where('publication_researcher.state', 'confirmed')])->orderBy('name')->paginate(12, ['*'], 'people'),
            'projects' => $line->projects()->with('responsible')->orderBy('name')->paginate(12, ['*'], 'projects'),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('lines.edit', ['line' => new ResearchLine]);
    }

    public function edit(ResearchLine $line): View
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        return view('lines.edit', ['line' => $line->loadCount(['researchers', 'projects'])]);
    }

    public function save(Request $request, ?ResearchLine $line = null): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate([
            'program' => 'nullable|string|max:250',
            'code' => ['nullable', 'string', 'max:64', 'regex:/\A[A-Z0-9-]+\z/i'],
        ]);
        $program = $data['program'] ?? ($request->has('program') ? '' : ($line?->program ?? ''));
        $request->merge(['code' => isset($data['code']) ? Str::upper($data['code']) : ($request->has('code') ? null : $line?->code)]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:250', Rule::unique('research_lines')->where('program', $program)->ignore($line?->id)],
            'code' => ['nullable', Rule::unique('research_lines')->ignore($line?->id)],
        ]) + ['program' => $program];
        $saved = DB::transaction(function () use ($line, $data) {
            $record = $line ? ResearchLine::lockForUpdate()->findOrFail($line->id) : new ResearchLine;
            $before = $record->exists ? $record->toArray() : null;
            $record->fill($data)->save();
            Audit::record('research_line', $record->id, $before ? 'updated' : 'created', $before, $record->toArray());

            return $record;
        });

        return redirect()->route('lines.show', $saved)->with('success', 'Línea de investigación guardada.');
    }

    public function destroy(Request $request, ResearchLine $line): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $request->validate(['confirm_delete' => 'accepted']);

        DB::transaction(function () use ($line) {
            $record = ResearchLine::lockForUpdate()->findOrFail($line->id);
            $before = $record->toArray() + [
                'researcher_ids' => $record->researchers()->pluck('researchers.id')->all(),
                'project_ids' => $record->projects()->pluck('research_projects.id')->all(),
            ];
            $record->delete();
            Audit::record('research_line', $record->id, 'deleted', $before);
        });

        return redirect()->route('admin.lines')->with('success', 'Línea de investigación eliminada.');
    }
}
