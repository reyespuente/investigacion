<div class="review-summary"><span class="badge">Revisión individual · #{{ $publication->id }}</span><h2>{{ $publication->title }}</h2><p class="muted">{{ $publication->year }} · {{ $publication->venue }}</p>
<div class="button-row"><button class="button small-button" type="submit" form="review-form-{{ $publication->id }}" name="intent" value="next" data-review-primary>{{ $administrator?'Publicar y siguiente':'Confirmar, publicar y siguiente' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></button>@if($publication->external_url)<a class="button secondary small-button" href="{{ $publication->external_url }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Consultar original</a>@endif
@if($administrator)<a class="text-link" href="{{ route('editor.edit',$publication) }}">Edición completa</a>@endif</div></div>
@if($publication->reviewGaps())<div class="notice error">Completa antes de publicar: {{ implode(', ',$publication->reviewGaps()) }}.</div>@endif
<details class="review-evidence"><summary>Autores de la referencia ({{ count($publication->authors) }})</summary><ol>@foreach($publication->authors as $author)<li><strong>{{ $author['nombre'] }}</strong><span class="small muted"> {{ implode('; ',$author['afiliaciones']??[]) }}</span>@if(!empty($author['orcid']))<span class="small"> · ORCID: {{ $author['orcid'] }}</span>@endif</li>@endforeach</ol></details>
<details class="review-evidence" @if(!$administrator) open @endif><summary>Resumen y procedencia</summary><div class="review-abstract">{{ $publication->abstract?:'La fuente no proporciona resumen.' }}</div><p class="small muted">Fuente: {{ $publication->origin }} · DOI: {{ $publication->doi?:'No declarado' }}. Consulta el original para contrastar los datos.</p></details>
<form id="review-form-{{ $publication->id }}" action="{{ route($administrator?'admin.review':'editor.confirm',$publication) }}" method="post" class="review-form">
@csrf<input name="version" type="hidden" value="{{ $publication->revision }}">
@foreach(request()->only(['status','q','researcher','ready']) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
@if($administrator)
<fieldset class="review-authors"><legend>Confirmar perfiles de docentes</legend><p class="small muted">Marca únicamente las coincidencias que hayas comprobado. Los autores bibliográficos se conservan aunque no tengan perfil aquí.</p>
@forelse($publication->researchers as $person)
@if($person->pivot->state==='confirmed')<p class="confirmed-person"><i class="bi bi-check-circle" aria-hidden="true"></i> {{ $person->name }} <span class="badge">Confirmado</span></p>
@else<label class="checkbox"><input type="checkbox" name="confirmed_ids[]" value="{{ $person->id }}" @checked(in_array($person->id,old('confirmed_ids',[])))> {{ $person->name }} <span class="badge">{{ $person->pivot->state==='rejected'?'Rechazado previamente':'Candidato' }}</span></label>@endif
@empty<p class="small muted">No hay perfiles propuestos para esta referencia.</p>@endforelse
<details><summary>Vincular otro docente</summary><label>Perfil comprobado<select name="researcher_id"><option value="">Sin añadir otro perfil</option>@foreach($researchers as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label></details>
</fieldset>
@else<input type="hidden" name="decision" value="published"><label class="checkbox authorship-consent"><input type="checkbox" name="authorship" value="1" required> He revisado la referencia y confirmo que soy autor/a de este trabajo.</label>@endif
<details class="review-evidence" @if($publication->reviewGaps()) open @endif><summary>Corregir datos esenciales</summary><div class="form-grid">
@include('partials.field',['name'=>'title','label'=>'Título','value'=>$publication->title,'required'=>true])
@include('partials.field',['name'=>'year','label'=>'Año','type'=>'number','value'=>$publication->year,'required'=>true])
@include('partials.field',['name'=>'venue','label'=>'Revista o congreso','value'=>$publication->venue,'required'=>true])
@include('partials.field',['name'=>'external_url','label'=>'Enlace al trabajo','type'=>'url','value'=>$publication->external_url,'required'=>true])
</div></details>
@if($administrator)<label>Decisión<select name="decision" data-review-decision><option value="published">Publicar con las autorías seleccionadas</option><option value="review">Mantener en revisión</option><option value="rejected">Rechazar referencia</option></select></label><label>Nota de revisión <span class="small muted">(obligatoria al rechazar o mantener en revisión)</span><textarea name="reason" rows="2" placeholder="Opcional al publicar; queda en el historial">{{ old('reason') }}</textarea></label>@endif
<div class="review-actions"><button class="button" type="submit" name="intent" value="next" data-review-primary>{{ $administrator?'Publicar y siguiente':'Confirmar, publicar y siguiente' }} <i class="bi bi-arrow-right" aria-hidden="true"></i></button><button class="button secondary" type="submit" name="intent" value="stay">{{ $administrator?'Guardar decisión':'Publicar y volver' }}</button></div>
<p class="small muted">Los cambios y las confirmaciones de autoría se guardan juntos, con historial. Los perfiles no seleccionados conservan su estado.</p>
</form>
