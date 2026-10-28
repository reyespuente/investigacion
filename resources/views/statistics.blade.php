@extends('layouts.app')
@section('title','Estadísticas')
@section('content')
<div class="workspace-heading"><div><p class="eyebrow">EL CONOCIMIENTO EN PERSPECTIVA</p><h1>Explora la investigación</h1><p class="muted">Selecciona un año, tipo o revista en las gráficas para explorar el catálogo.</p></div></div>
<form method="get" class="queue-filters" data-statistics-form>
<label>Año<select name="year"><option value="">Todos los años</option>@foreach($allYears as $year)<option @selected(request('year')==$year)>{{ $year }}</option>@endforeach</select></label>
<label>Tipo<select name="type"><option value="">Todos los tipos</option>@foreach(config('catalogue.types') as $key=>$label)<option value="{{ $key }}" @selected(request('type')===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Revista o congreso<input name="venue" type="search" value="{{ request('venue') }}"></label><button class="button secondary">Aplicar</button><a href="{{ route('statistics') }}">Restablecer</a>
</form>
<p class="small muted">Solo publicaciones públicas y autorías confirmadas. Las cifras describen la cobertura del repositorio; no miden calidad ni productividad total.</p>
<div role="alert" class="notice error" data-statistics-error hidden>No pudimos actualizar las gráficas. Intenta nuevamente.</div>
<div id="statistics-results" aria-live="polite">@include('partials.statistics')</div>
@endsection
