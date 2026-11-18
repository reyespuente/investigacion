@extends('layouts.app')
@section('title', $project->name)
@section('content')
<a class="back-link" href="{{ route('projects.index') }}">Proyectos de investigación</a>
<div class="section-heading page-heading">
    <h1>{{ $project->name }}</h1>
    @if(auth()->user()?->role === 'admin')<a class="button secondary" href="{{ route('admin.projects.edit', $project) }}">Editar proyecto</a>@endif
</div>
<div class="academic-detail-grid">
    <article class="academic-section"><h2>Acerca del proyecto</h2><p class="long-text">{{ $project->description }}</p>
        <h2>Líneas de investigación</h2>
        <div class="tags">@forelse($project->researchLines as $line)<a href="{{ route('lines.show', $line) }}">{{ $line->name }}</a>@empty<p class="muted">Sin líneas vinculadas.</p>@endforelse</div>
    </article>
    <aside class="academic-section"><h2>Participantes <span class="muted">{{ $participants->count() }}/5</span></h2>
        <ol class="participant-list">
            @forelse($participants as $person)
            <li>@if($person->researcher_id)<a href="{{ route('researchers.show', $person->researcher_id) }}">{{ $person->profile_name }}</a>
                @if($person->department)<small>{{ $person->department }}</small>@endif
                @else<strong>{{ $person->name }}</strong><small>Participante externo</small>@endif</li>
            @empty<li class="muted">Participantes por registrar.</li>@endforelse
        </ol>
    </aside>
</div>
@endsection
