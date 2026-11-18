@extends('layouts.app')
@section('title', $project->exists ? 'Editar proyecto' : 'Crear proyecto')
@section('content')
<a class="back-link" href="{{ route('admin.projects') }}">Proyectos</a>
<div class="page-heading"><h1>{{ $project->exists ? 'Editar proyecto' : 'Crear proyecto' }}</h1></div>
<form class="form-panel wide-form" method="post" action="{{ $project->exists ? route('admin.projects.update', $project) : route('admin.projects.store') }}">
    @csrf
    <input type="hidden" name="version" value="{{ $project->revision ?? 0 }}">
    <input type="hidden" name="participants_present" value="1">
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre del proyecto', 'value' => $project->name, 'required' => true])
    @include('partials.field', ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rows' => 6, 'value' => $project->description, 'required' => true])
    @php
        $participantRows = session()->hasOldInput('participants_present') ? old('participants', []) : $participants->map(fn ($person) => ['name' => $person->profile_name ?? $person->name, 'researcher_id' => $person->researcher_id])->all();
        $self = null;
    @endphp
    <fieldset class="author-editor" data-author-editor data-url="{{ route('authors.suggestions') }}" data-field="participants" data-limit="5" data-people-label="participantes">
        <legend>Participantes · máximo 5</legend>
        <ol class="author-editor-list" data-author-list>
            @foreach($participantRows as $index => $author)
                @include('partials.author-row', ['field' => 'participants', 'showAffiliations' => false])
            @endforeach
        </ol>
        <label class="field" for="project-participant-search"><span>Buscar investigador de la plataforma</span><input id="project-participant-search" type="search" autocomplete="off" maxlength="150" placeholder="Nombre o apellido" aria-controls="project-suggestions" data-author-search></label>
        <p class="small muted" role="status" aria-live="polite" data-author-status></p>
        <ul id="project-suggestions" class="author-suggestions" data-author-suggestions hidden></ul>
        <label class="field"><span>Participantes externos</span><textarea name="external_participants" rows="2" maxlength="3000" data-external-authors>{{ old('external_participants') }}</textarea><small class="muted">Nombres completos separados por comas. Los participantes internos y externos cuentan para el límite de 5.</small></label>
        <button class="button secondary small-button" type="button" data-add-external>Agregar participantes externos</button>
        <template data-author-template>@include('partials.author-row', ['author' => [], 'index' => '__INDEX__', 'field' => 'participants', 'showAffiliations' => false])</template>
    </fieldset>
    <fieldset class="academic-section"><legend>Líneas de investigación</legend>
        @forelse($lines as $line)<label class="checkbox"><input type="checkbox" name="research_lines[]" value="{{ $line->id }}" @checked(in_array($line->id, old('research_lines', $project->exists ? $project->researchLines->modelKeys() : [])))>{{ $line->name }}</label>
        @empty<p class="muted">Puedes crear líneas desde el catálogo de investigación.</p>@endforelse
    </fieldset>
    <button class="button">Guardar proyecto</button>
</form>
@endsection
