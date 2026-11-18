@php
    $self = ($administrator ?? (auth()->user()->role === 'admin')) ? null : auth()->user()->researcher;
    $initialAuthors = app(\App\Services\PublicationAuthors::class)->forEditor($publication, $self);
    $authorRows = session()->hasOldInput('authors_editor')
        ? old('authors', [])
        : array_map(fn ($author) => [
            'name' => $author['nombre'],
            'researcher_id' => $author['researcher_id'] ?? '',
            'affiliations' => implode('; ', $author['afiliaciones'] ?? []),
        ], $initialAuthors);
    $editorId = 'authors-'.($publication->id ?? 'new');
@endphp
<fieldset class="author-editor" data-author-editor data-url="{{ route('authors.suggestions') }}" data-self-id="{{ $self?->id }}" data-self-name="{{ $self?->name }}">
    <legend>Autores en orden de publicación</legend>
    <input type="hidden" name="authors_editor" value="1">
    @if($self)<p class="small muted">Tu perfil está incluido como autor. Ordena los nombres como aparecen en el trabajo.</p>@endif
    <ol class="author-editor-list" data-author-list>
        @foreach($authorRows as $index => $author)
            @include('partials.author-row', compact('author', 'index', 'self'))
        @endforeach
    </ol>
    <label class="field" for="{{ $editorId }}-search"><span>Buscar investigador de la plataforma</span>
        <input id="{{ $editorId }}-search" type="search" autocomplete="off" placeholder="Escribe al menos dos caracteres" maxlength="150" aria-controls="{{ $editorId }}-suggestions" data-author-search>
    </label>
    <p class="small muted" role="status" aria-live="polite" data-author-status></p>
    <ul id="{{ $editorId }}-suggestions" class="author-suggestions" data-author-suggestions hidden></ul>
    <label class="field" for="{{ $editorId }}-external"><span>Autores externos</span>
        <textarea id="{{ $editorId }}-external" name="external_authors" rows="2" maxlength="100000" placeholder="María López Pérez, Juan García Ruiz" data-external-authors>{{ old('external_authors') }}</textarea>
        <small class="muted">Escribe sus nombres completos separados por comas o saltos de línea. Se agregarán al final de la lista.</small>
    </label>
    <button class="button secondary small-button" type="button" data-add-external>Agregar autores externos a la lista</button>
    <template data-author-template>
        @include('partials.author-row', ['author' => [], 'index' => '__INDEX__', 'self' => null])
    </template>
</fieldset>
