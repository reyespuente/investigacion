@extends('layouts.app')
@section('title', $project->name)
@section('content')
<a class="back-link" href="{{ route('projects.index') }}">Proyectos de investigación</a>
@php
    $formatPeriod = fn ($period) => $period && strlen($period) > 4
        ? \Illuminate\Support\Facades\Date::parse($period)->locale('es')->translatedFormat('j \d\e F \d\e Y')
        : $period;
    $details = array_filter([
        'Departamento' => $project->department,
        'Institución financiadora' => $project->funding_institution,
        'Convocatoria' => $project->funding_call,
        'Inicio de vigencia' => $formatPeriod($project->period_start),
        'Fin de vigencia' => $formatPeriod($project->period_end),
    ]);
@endphp
<div class="section-heading page-heading">
    <div><p class="eyebrow">PROYECTO DE INVESTIGACIÓN</p><h1>{{ $project->name }}</h1></div>
    @can('update', $project)<a class="button secondary" href="{{ route(auth()->user()->role === 'admin' ? 'admin.projects.edit' : 'projects.edit', $project) }}">Editar proyecto</a>@endcan
</div>
<div class="academic-detail-grid">
    <div class="project-detail-content">
        @if($details)
        <section class="academic-section">
            <h2>Datos del proyecto</h2>
            <dl class="project-facts">
                @foreach($details as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
        @endif
        <section class="academic-section">
            <h2>Descripción de la investigación</h2>
            @if($project->description)
                <p class="long-text project-description">{{ $project->description }}</p>
            @else
                <p class="muted project-description">Aún no se ha publicado una descripción.</p>
            @endif
        </section>
    </div>
    <aside class="academic-section project-team"><h2>Equipo de investigación</h2>
        <h3>Responsable técnico</h3>
        @if($project->responsible)
            <p><a href="{{ route('researchers.show', $project->responsible) }}">{{ $project->responsibleDisplayName() }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></p>
        @else
            <p>{{ $project->responsibleDisplayName() ?? 'Por registrar.' }}</p>
        @endif
        <h3>Participantes</h3>
        @if($participants->isNotEmpty())
        <ol class="participant-list">
            @foreach($participants as $person)
            <li>@if($person->researcher_id)<a href="{{ route('researchers.show', $person->researcher_id) }}">{{ $person->profile_name }}</a>
                @if($person->department)<small>{{ $person->department }}</small>@endif
                @else<strong>{{ $person->name }}</strong><small>Participante externo</small>@endif</li>
            @endforeach
        </ol>
        @else
            <p class="small muted">No hay participantes adicionales registrados.</p>
        @endif
    </aside>
</div>
@endsection
