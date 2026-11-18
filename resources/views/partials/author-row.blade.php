@php
    $name = $author['name'] ?? '';
    $profileId = $author['researcher_id'] ?? '';
    $isSelf = $self && (string) $profileId === (string) $self->id;
@endphp
<li class="author-editor-row" data-author-row data-self="{{ $isSelf ? '1' : '0' }}">
    <div class="author-editor-fields">
        <label class="field">
            <span>Nombre completo @if($isSelf)<span class="badge">Tú</span>@elseif($profileId)<span class="badge">Perfil seleccionado</span>@endif</span>
            <input type="text" name="authors[{{ $index }}][name]" value="{{ $name }}" maxlength="500" required @readonly($profileId) data-author-name>
        </label>
        <input type="hidden" name="authors[{{ $index }}][researcher_id]" value="{{ $profileId }}" data-author-profile>
        <details>
            <summary>Afiliaciones</summary>
            <label class="field"><span>Instituciones, separadas por punto y coma</span>
                <input type="text" name="authors[{{ $index }}][affiliations]" value="{{ $author['affiliations'] ?? '' }}" maxlength="10000" data-author-affiliations>
            </label>
        </details>
    </div>
    <div class="author-editor-actions">
        <button class="button secondary small-button" type="button" data-author-up aria-label="Subir autor en el orden"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
        <button class="button secondary small-button" type="button" data-author-down aria-label="Bajar autor en el orden"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
        <button class="text-button" type="button" data-author-remove @disabled($isSelf) aria-label="Quitar autor"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
</li>
