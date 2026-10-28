@extends('layouts.app')
@section('title','Revisar publicaciones')
@section('content')
<div class="workspace-heading"><div><p class="eyebrow">TU BANDEJA DE TRABAJO</p><h1>Revisar publicaciones</h1><p class="muted">Comprueba la referencia y confirma sus autorías en un solo paso.</p></div><a class="button secondary" href="{{ route('admin.system') }}"><i class="bi bi-gear" aria-hidden="true"></i>Configuración</a></div>
<nav class="status-tabs" aria-label="Estados de publicación">
@foreach(['review'=>'En revisión','incomplete'=>'Incompletas','published'=>'Publicadas','rejected'=>'Rechazadas'] as $key=>$label)
<a href="{{ route('admin',['status'=>$key]) }}" @class(['active'=>request('status','review')===$key])>{{ $label }} <strong>{{ number_format($counts[$key]??0) }}</strong></a>
@endforeach</nav>
<form method="get" class="queue-filters"><input type="hidden" name="status" value="{{ request('status','review') }}">
<label>Buscar referencia<input type="search" name="q" value="{{ request('q') }}" placeholder="Título, autor o tema"></label>
<label>Docente<select name="researcher"><option value="">Todos</option>@foreach($researchers as $person)<option value="{{ $person->id }}" @selected(request('researcher')==$person->id)>{{ $person->name }}</option>@endforeach</select></label>
<label>Metadatos<select name="ready"><option value="">Todos</option><option value="1" @selected(request('ready')==='1')>Datos esenciales completos</option><option value="2" @selected(request('ready')==='2')>Aptas para revisión por lote</option><option value="0" @selected(request('ready')==='0')>Faltan datos</option></select></label>
<button class="button secondary">Filtrar</button><a href="{{ route('admin') }}" class="text-link">Limpiar</a></form>
@if(in_array(request('status','review'),['review','incomplete']))
<form id="batch-review" method="post" action="{{ route('admin.batch') }}" class="batch-bar">@csrf
@foreach(request()->only(['status','q','researcher','ready']) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<div><strong><span data-selection-count>0</span> seleccionadas</strong><label class="checkbox"><input type="checkbox" name="checked" value="1" required> He comprobado estas referencias y sus autorías.</label><p class="small muted">Solo se pueden seleccionar fichas completas con perfiles confirmados y sin candidatos pendientes.</p></div><div class="button-row"><button class="button secondary small-button" type="button" data-batch-all>Seleccionar aptas de esta página</button><button class="button secondary" data-batch-submit type="submit">Publicar seleccionadas</button></div>
</form>@endif
<div class="review-workspace"><section class="queue-list" aria-label="Cola de publicaciones"><div class="queue-caption"><strong>{{ number_format($publications->total()) }}</strong> resultados · 25 por página</div>
@forelse($publications as $publication)
<div class="queue-entry">@if($publication->batchReviewReady())<input type="checkbox" form="batch-review" name="items[{{ $publication->id }}]" value="{{ $publication->revision }}" data-batch-item aria-label="Seleccionar referencia {{ $publication->id }} para publicar">@endif
<a href="{{ route('admin',array_merge(request()->only(['status','q','researcher','ready','page']),['paper'=>$publication->id])) }}" @class(['queue-row','selected'=>$selected?->id===$publication->id]) @if($selected?->id===$publication->id) aria-current="true" @endif>
<span class="publication-meta">#{{ $publication->id }} · {{ $publication->year??'Sin año' }} · {{ $publication->label() }}</span><strong>{{ $publication->title }}</strong><span class="small muted">{{ Str::limit($publication->venue,85) }}</span>
<span class="queue-flags"><span class="badge">{{ $publication->reviewGaps()?'Faltan datos':'Datos completos' }}</span><span>{{ $publication->researchers->where('pivot.state','confirmed')->count() }} confirmadas · {{ $publication->researchers->where('pivot.state','pending')->count() }} pendientes</span></span></a></div>
@empty<div class="empty"><h2>Bandeja despejada</h2><p>No hay publicaciones con estos filtros.</p></div>@endforelse
{{ $publications->links() }}</section>
<section class="review-detail" aria-label="Revisión individual">
@if($selected) @include('partials.review',['publication'=>$selected,'administrator'=>true])
@else<div class="empty"><h2>Todo listo por aquí</h2><p>Selecciona otro estado o cambia los filtros.</p></div>@endif
</section></div>
@endsection
