@extends('layouts.app')
@section('title', $adminEditing ? 'Administrar perfil académico' : 'Editar perfil académico')
@section('content')
<a class="back-link" href="{{ route($adminEditing ? 'researchers.index' : 'account') }}">{{ $adminEditing ? 'Investigadores' : 'Mi espacio' }}</a>
<div class="section-heading page-heading"><div><h1>{{ $adminEditing ? 'Editar perfil de '.$researcher->name : 'Tu perfil académico' }}</h1><p class="muted">{{ $adminEditing ? 'Los cambios se mostrarán en el perfil público del investigador.' : 'Presenta tu trayectoria, formación y áreas de investigación.' }}</p></div><a class="button secondary" href="{{ route('researchers.show', $researcher) }}">Ver perfil público</a></div>
<nav class="profile-section-nav" aria-label="Secciones del perfil"><a href="#presentation">Presentación</a><a href="#education">Formación</a><a href="#recognitions">Reconocimientos</a><a href="#research-lines">Investigación</a><a href="#contact">Contacto</a></nav>
<form class="academic-profile-form" method="post" action="{{ $adminEditing ? route('admin.researchers.update', $researcher) : route('profile.update') }}" enctype="multipart/form-data">
    @csrf
    <section class="academic-section" id="presentation"><h2>Presentación</h2>
        @include('partials.field', ['name' => 'name', 'label' => 'Nombre público', 'value' => $researcher->name, 'required' => true])
        <div class="form-grid">
            @include('partials.field', ['name' => 'professional_title', 'label' => 'Título profesional o presentación breve', 'value' => $researcher->professional_title])
            @include('partials.field', ['name' => 'career', 'label' => 'Carrera o área de formación', 'value' => $researcher->career])
        </div>
        <label class="field" for="academic-department"><span>Departamento académico o área</span><input id="academic-department" name="department" list="academic-departments" value="{{ old('department', $researcher->department) }}" maxlength="250"><small class="muted">Selecciona una sugerencia o escribe el nombre de tu departamento.</small></label>
        <datalist id="academic-departments">@foreach(config('academic.departments') as $department)<option value="{{ $department }}">@endforeach</datalist>
        @include('partials.field', ['name' => 'bio', 'label' => 'Acerca de ti', 'type' => 'textarea', 'rows' => 5, 'value' => $researcher->bio])
        @include('partials.field', ['name' => 'avatar', 'label' => 'Foto de perfil (JPG, PNG o WebP, hasta 3 MB)', 'type' => 'file'])
        @if($adminEditing)
            @include('partials.field', ['name' => 'orcid', 'label' => 'URL de ORCID (https://orcid.org/…)', 'type' => 'url', 'value' => $researcher->orcid])
        @endif
    </section>
    <section class="academic-section" id="education" data-degrees-editor><h2>Formación académica</h2><p class="small muted">Puedes registrar varios grados, incluso del mismo nivel.</p>
        <input type="hidden" name="degrees_present" value="1">
        <div data-degrees-list>
            @foreach(session()->hasOldInput('degrees_present') ? old('degrees', []) : ($researcher->degrees ?? []) as $index => $degree)
                @include('account.degree-row')
            @endforeach
        </div>
        <button class="button secondary small-button" type="button" data-add-degree>Agregar grado académico</button>
        <p class="small muted" role="status" data-degrees-status></p>
        <template data-degree-template>@include('account.degree-row', ['degree' => [], 'index' => '__INDEX__'])</template>
    </section>
    <section class="academic-section" id="recognitions"><h2>Reconocimientos</h2>
        <input type="hidden" name="recognitions_present" value="1">
        <label class="checkbox"><input type="checkbox" name="prodep" value="1" @checked(old('recognitions_present') ? old('prodep') : !empty($researcher->recognitions['prodep'])) data-prodep-toggle> Cuento con reconocimiento PRODEP</label>
        <div class="form-grid" data-prodep-dates>
            @include('partials.field', ['name' => 'prodep_start', 'label' => 'Inicio de vigencia PRODEP', 'type' => 'date', 'value' => $researcher->recognitions['prodep']['start'] ?? ''])
            @include('partials.field', ['name' => 'prodep_until', 'label' => 'Fin de vigencia PRODEP', 'type' => 'date', 'value' => $researcher->recognitions['prodep']['until'] ?? ''])
        </div>
        @include('partials.field', ['name' => 'snii_level', 'label' => 'Nivel SNII', 'type' => 'select', 'value' => $researcher->recognitions['snii'] ?? '', 'options' => ['' => 'No aplica'] + array_combine(config('academic.snii_levels'), config('academic.snii_levels'))])
    </section>
    <section class="academic-section" id="research-lines"><h2>Líneas de investigación</h2>
        <input type="hidden" name="research_lines_present" value="1">
        <p class="small muted">{{ $adminEditing ? 'Selecciona las líneas a las que se adscribe el investigador.' : 'Elige las líneas a las que perteneces.' }} Las adscripciones se mostrarán en el perfil público.</p>
        @php($selectedLines = session()->hasOldInput('research_lines_present') ? (array) old('research_lines', []) : $researcher->researchLines->modelKeys())
        @forelse($lines->groupBy('program') as $program => $programLines)
            <fieldset class="line-program">
                <legend>{{ $program ?: 'Sin programa registrado' }}</legend>
                <div class="line-options">
                    @foreach($programLines as $line)
                        <label class="checkbox"><input type="checkbox" name="research_lines[]" value="{{ $line->id }}" @checked(in_array($line->id, $selectedLines))><span>{{ $line->name }}@if($line->code) <small class="muted">({{ $line->code }})</small>@endif</span></label>
                    @endforeach
                </div>
            </fieldset>
        @empty
            <p class="muted">El administrador aún no ha registrado líneas.</p>
        @endforelse
    </section>
    <section class="academic-section" id="contact"><h2>Contacto público</h2>
        <p class="small muted">Los datos de contacto de esta sección aparecerán en el perfil público.</p>
        @include('account.public-emails')
        @include('partials.field', ['name' => 'website', 'label' => 'Sitio web, LinkedIn u otro perfil académico', 'type' => 'url', 'value' => $researcher->contacts['website'] ?? ''])
        @include('partials.field', ['name' => 'phone', 'label' => 'Teléfono público (opcional)', 'value' => $researcher->contacts['phone'] ?? ''])
    </section>
    <div class="profile-save-bar"><button class="button">Guardar perfil público</button><a href="{{ route('researchers.show', $researcher) }}">Ver perfil público</a></div>
</form>
@endsection
