<?php

namespace App\Http\Controllers;

use App\Models\ResearchLine;
use App\Services\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResearchLineController extends Controller
{
    public function index(Request $request): View
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()?->role === 'admin', 403);
        }
        $request->validate(['q' => 'nullable|string|max:200']);
        $query = ResearchLine::withCount(['researchers', 'projects'])->orderBy('name');
        if ($request->filled('q')) {
            $query->whereLike('name', '%'.$request->q.'%');
        }

        return view('lines.index', ['lines' => $query->paginate(20)->withQueryString()]);
    }

    public function show(ResearchLine $line): View
    {
        return view('lines.show', [
            'line' => $line,
            'researchers' => $line->researchers()->withCount(['publications' => fn ($query) => $query->published()->where('publication_researcher.state', 'confirmed')])->orderBy('name')->paginate(12, ['*'], 'people'),
            'projects' => $line->projects()->orderBy('name')->paginate(12, ['*'], 'projects'),
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

        return view('lines.edit', compact('line'));
    }

    public function save(Request $request, ?ResearchLine $line = null): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:250', Rule::unique('research_lines')->ignore($line?->id)]]);
        $saved = DB::transaction(function () use ($line, $data) {
            $record = $line ? ResearchLine::lockForUpdate()->findOrFail($line->id) : new ResearchLine;
            $before = $record->exists ? $record->toArray() : null;
            $record->fill($data)->save();
            Audit::record('research_line', $record->id, $before ? 'updated' : 'created', $before, $record->toArray());

            return $record;
        });

        return redirect()->route('lines.show', $saved)->with('success', 'Línea de investigación guardada.');
    }
}
