@extends('layouts.app')
@section('title', $project->exists ? 'Editar proyecto' : 'Crear proyecto')
@section('content')
<a class="back-link" href="{{ $managingTeam ? route('admin.projects') : route('projects.show', $project) }}">Proyectos</a>
<div class="page-heading"><h1>{{ $project->exists ? 'Editar proyecto' : 'Crear proyecto' }}</h1></div>
<form class="form-panel wide-form" method="post" action="{{ $project->exists ? route($managingTeam ? 'admin.projects.update' : 'projects.update', $project) : route('admin.projects.store') }}">
    @csrf
    <input type="hidden" name="version" value="{{ $project->revision ?? 0 }}">
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre del proyecto', 'value' => $project->name, 'required' => true])
    @include('partials.field', ['name' => 'description', 'label' => 'Descripción', 'type' => 'textarea', 'rows' => 6, 'value' => $project->description])
    <div class="form-grid">
        @include('partials.field', ['name' => 'department', 'label' => 'Departamento', 'value' => $project->department])
        @include('partials.field', ['name' => 'funding_institution', 'label' => 'Institución financiadora', 'value' => $project->funding_institution])
        @include('partials.field', ['name' => 'funding_call', 'label' => 'Convocatoria', 'value' => $project->funding_call])
    </div>
    <div class="form-grid">
        @include('partials.field', ['name' => 'period_start', 'label' => 'Inicio de vigencia', 'help' => 'Año (AAAA) o fecha (AAAA-MM-DD).', 'value' => $project->period_start])
        @include('partials.field', ['name' => 'period_end', 'label' => 'Fin de vigencia', 'help' => 'Año (AAAA) o fecha (AAAA-MM-DD).', 'value' => $project->period_end])
    </div>
    @if($managingTeam)
    <fieldset class="academic-section">
        <legend>Responsable técnico</legend>
        @include('partials.field', ['name' => 'responsible_name', 'label' => 'Nombre del responsable sin perfil vinculado', 'value' => $project->responsible_name])
        <label class="field"><span>Vincular perfil del responsable</span>
            <select name="responsible_researcher_id">
                <option value="">Sin vincular: publicar solo el nombre</option>
                @foreach($researchers as $researcher)
                    <option value="{{ $researcher->id }}" @selected(old('responsible_researcher_id', $project->responsible_researcher_id) == $researcher->id)>{{ $researcher->name }}@if($researcher->department) · {{ $researcher->department }}@endif</option>
                @endforeach
            </select>
            <small class="muted">El perfil seleccionado sustituye el nombre escrito y su titular podrá editar la ficha pública. Solo la administración puede cambiar el equipo.</small>
        </label>
    </fieldset>
    <input type="hidden" name="participants_present" value="1">
    @php
        $participantRows = session()->hasOldInput('participants_present') ? (array) old('participants', []) : $participants->map(fn ($person) => ['name' => $person->profile_name ?? $person->name, 'researcher_id' => $person->researcher_id])->all();
        $self = null;
    @endphp
    <fieldset class="author-editor" data-author-editor data-url="{{ route('authors.suggestions') }}" data-field="participants" data-limit="4" data-people-label="participantes">
        <legend>Participantes adicionales · máximo 4</legend>
        <ol class="author-editor-list" data-author-list>
            @foreach($participantRows as $index => $author)
                @include('partials.author-row', ['field' => 'participants', 'showAffiliations' => false])
            @endforeach
        </ol>
        <label class="field" for="project-participant-search"><span>Buscar investigador de la plataforma</span><input id="project-participant-search" type="search" autocomplete="off" maxlength="150" placeholder="Nombre o apellido" aria-controls="project-suggestions" data-author-search></label>
        <p class="small muted" role="status" aria-live="polite" data-author-status></p>
        <ul id="project-suggestions" class="author-suggestions" data-author-suggestions hidden></ul>
        <label class="field"><span>Participantes externos</span><textarea name="external_participants" rows="2" maxlength="3000" data-external-authors>{{ old('external_participants') }}</textarea><small class="muted">Nombres completos separados por comas. Los participantes internos y externos cuentan para el límite de cuatro.</small></label>
        <button class="button secondary small-button" type="button" data-add-external>Agregar participantes externos</button>
        <template data-author-template>@include('partials.author-row', ['author' => [], 'index' => '__INDEX__', 'field' => 'participants', 'showAffiliations' => false])</template>
    </fieldset>
    @else
        <p class="muted">Solo la administración puede cambiar al responsable y a los demás participantes.</p>
    @endif
    <fieldset class="academic-section"><legend>Líneas de investigación</legend>
        <input type="hidden" name="research_lines_present" value="1">
        @php($selectedLines = session()->hasOldInput('research_lines_present') ? (array) old('research_lines', []) : $project->researchLines->modelKeys())
        @forelse($lines as $line)<label class="checkbox"><input type="checkbox" name="research_lines[]" value="{{ $line->id }}" @checked(in_array($line->id, $selectedLines))>{{ $line->label() }}</label>
        @empty<p class="muted">Puedes crear líneas desde el catálogo de investigación.</p>@endforelse
    </fieldset>
    <button class="button">Guardar proyecto</button>
</form>
@if($managingTeam && $project->exists)
    <form class="form-panel" method="post" action="{{ route('admin.projects.destroy', $project) }}">
        @csrf
        @method('DELETE')
        <input type="hidden" name="version" value="{{ $project->revision }}">
        <h2>Eliminar proyecto</h2>
        <p>Se retirará su ficha pública. No se eliminarán los perfiles ni las líneas de investigación vinculadas.</p>
        <label class="checkbox"><input type="checkbox" name="confirm_delete" value="1" required> Confirmo que deseo eliminar este proyecto.</label>
        <button class="button secondary">Eliminar proyecto</button>
    </form>
@endif
@endsection
