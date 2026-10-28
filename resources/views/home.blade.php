
@extends('layouts.app')
@section('content')
<section class="hero">
<p class="eyebrow">CONOCIMIENTO ABIERTO · COMUNIDAD</p>
<h1>La investigación que <br>nos mueve <span>hacia adelante.</span></h1>
<p class="hero-description">Explora las ideas, las personas y los descubrimientos de nuestra comunidad.</p>
<form action="{{ route('publications.index') }}" class="search-form" data-home-search>
<label class="search-box"><i class="bi bi-search" aria-hidden="true"></i><input name="q" type="search" autocomplete="off" placeholder="Busca por título, autor, tema o DOI" aria-label="Buscar publicaciones"><span class="search-hint">Enter <i class="bi bi-arrow-return-left" aria-hidden="true"></i></span></label>
<div class="filter-row"><label>Buscar en<select name="scope"><option value="">Todo el catálogo</option><option value="title">Título del trabajo</option><option value="author">Autores</option><option value="tag">Etiquetas</option></select></label>
<label>Año<select name="year"><option value="">Todos los años</option>
@foreach($years as $year)<option>{{ $year }}</option>
@endforeach</select></label>
<label>Tipo<select name="type"><option value="">Todas las publicaciones</option>
@foreach(config('catalogue.types') as $k=>$label)<option value="{{ $k }}">{{ $label }}</option>
@endforeach</select></label>
<details class="advanced-search"><summary><i class="bi bi-funnel" aria-hidden="true"></i> Más filtros</summary><div class="filter-row"><label>Revista o congreso<input type="search" name="venue" placeholder="Nombre de la publicación"></label><label class="checkbox"><input name="doi" type="checkbox" value="1"> Con DOI</label><label class="checkbox"><input name="abstract" type="checkbox" value="1"> Con resumen</label></div></details><a class="text-link" href="{{ route('publications.index') }}">Explorar catálogo <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div></form>
<div class="metrics"><span><strong>{{ number_format($count) }}</strong> publicaciones disponibles</span><span><strong>{{ number_format($people) }}</strong> investigadores con publicaciones</span></div></section>
<div id="home-live-results" aria-live="polite" hidden></div>
<div id="home-default" class="discovery-grid">
<section>
<div class="section-heading">
<div>
<p class="eyebrow">EL CONOCIMIENTO SIGUE CRECIENDO</p>
<h2>Últimas publicaciones</h2></div><a class="text-link" href="{{ route('publications.index') }}">Ver todas <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
@if($latest->isNotEmpty())
@include('partials.card',['publication'=>$latest[0],'featured'=>true])<div class="card-grid">
@foreach($latest->skip(1) as $publication)
@include('partials.card',['featured'=>false])
@endforeach</div>
@else<p>Aún no hay publicaciones disponibles.</p>
@endif</section>
<aside class="ranking">
<p class="eyebrow">NUESTRA COMUNIDAD</p>
<h2>Investigadores destacados</h2>
<p class="muted small">Por publicaciones confirmadas en este catálogo</p><ol>
@foreach($ranking as $person)<li><span class="rank-number">{{ $loop->iteration }}</span><a href="{{ route('researchers.show',$person) }}"><span class="avatar">
@if($person->avatar_path)<img src="{{ route('researchers.avatar',$person) }}" alt="">
@else{{ $person->initials() }}
@endif</span><span><strong>{{ mb_convert_case($person->name,MB_CASE_TITLE,'UTF-8') }}</strong><small>{{ $person->publications_count }} publicaciones</small></span></a></li>
@endforeach</ol><a class="text-link" href="{{ route('researchers.index') }}">Conoce a la comunidad <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
<p class="ranking-note">Este orden refleja la cobertura del catálogo; no mide calidad ni impacto académico.</p></aside></div>
@endsection
