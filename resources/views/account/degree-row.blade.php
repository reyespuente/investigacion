<div class="degree-row" data-degree-row>
    <div class="form-grid">
        <label class="field"><span>Grado académico</span><select name="degrees[{{ $index }}][level]" required data-degree-field="level">
            @foreach(config('academic.degrees') as $level)<option value="{{ $level }}" @selected(($degree['level'] ?? '') === $level)>{{ $level }}</option>@endforeach
        </select></label>
        <label class="field"><span>Nombre del programa o título</span><input name="degrees[{{ $index }}][program]" value="{{ $degree['program'] ?? '' }}" maxlength="250" data-degree-field="program"></label>
        <label class="field"><span>Institución</span><input name="degrees[{{ $index }}][institution]" value="{{ $degree['institution'] ?? '' }}" maxlength="250" data-degree-field="institution"></label>
        <label class="field"><span>Año de obtención (opcional)</span><input type="number" name="degrees[{{ $index }}][year]" value="{{ $degree['year'] ?? '' }}" min="1800" max="{{ now()->year + 1 }}" data-degree-field="year"></label>
    </div>
    <button type="button" class="text-button dismiss-link" data-remove-degree>Quitar grado</button>
</div>
