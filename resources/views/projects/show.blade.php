@extends('layouts.app')
@section('title', $project->name)
@section('content')
<a class="back-link" href="{{ route('projects.index') }}">Proyectos de investigación</a>
<div class="section-heading page-heading">
    <h1>{{ $project->name }}</h1>
    @can('update', $project)<a class="button secondary" href="{{ route(auth()->user()->role === 'admin' ? 'admin.projects.edit' : 'projects.edit', $project) }}">Editar proyecto</a>@endcan
</div>
<div class="academic-detail-grid">
    <article class="academic-section"><h2>Acerca del proyecto</h2>
        @if($project->description)<p class="long-text">{{ $project->description }}</p>@endif
        @if($project->department || $project->funding_call || $project->funding_institution || $project->period_start || $project->period_end)
            <dl>
                @if($project->department)<dt>Departamento</dt><dd>{{ $project->department }}</dd>@endif
                @if($project->funding_call)<dt>Convocatoria</dt><dd>{{ $project->funding_call }}</dd>@endif
                @if($project->funding_institution)<dt>Institución financiadora</dt><dd>{{ $project->funding_institution }}</dd>@endif
                @if($project->period_start)<dt>Inicio de vigencia</dt><dd>{{ $project->period_start }}</dd>@endif
                @if($project->period_end)<dt>Fin de vigencia</dt><dd>{{ $project->period_end }}</dd>@endif
            </dl>
        @endif
        <h2>Líneas de investigación</h2>
        <div class="tags">@forelse($project->researchLines as $line)<a href="{{ route('lines.show', $line) }}">{{ $line->label() }}</a>@empty<p class="muted">Sin líneas vinculadas.</p>@endforelse</div>
    </article>
    <aside class="academic-section"><h2>Responsable técnico</h2>
        @if($project->responsible)
            <p><a href="{{ route('researchers.show', $project->responsible) }}">{{ $project->responsibleDisplayName() }}</a></p>
        @else
            <p>{{ $project->responsibleDisplayName() ?? 'Por registrar.' }}</p>
        @endif
        <h2>Participantes adicionales</h2>
        <ol class="participant-list">
            @forelse($participants as $person)
            <li>@if($person->researcher_id)<a href="{{ route('researchers.show', $person->researcher_id) }}">{{ $person->profile_name }}</a>
                @if($person->department)<small>{{ $person->department }}</small>@endif
                @else<strong>{{ $person->name }}</strong><small>Participante externo</small>@endif</li>
            @empty<li class="muted">No hay participantes adicionales registrados.</li>@endforelse
        </ol>
    </aside>
</div>
@endsection
