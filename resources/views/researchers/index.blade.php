
@extends('layouts.app')
@section('title','Investigadores')
@section('content')<div class="page-heading">
<p class="eyebrow">PERSONAS DETRÁS DEL CONOCIMIENTO</p>
<h1>Nuestra comunidad.</h1>
<p class="muted">Conoce las trayectorias y publicaciones del catálogo.</p>
<form action="{{ route('researchers.index') }}" class="directory-search">
<label class="search-box"><i class="bi bi-search" aria-hidden="true"></i><input name="q" type="search" value="{{ request('q') }}" placeholder="Nombre o apellido" aria-label="Buscar investigadores"></label></form></div>
<div class="researcher-grid">
@forelse($researchers as $person)<a class="researcher-card" href="{{ route('researchers.show',$person) }}"><span class="avatar large">
@if($person->avatar_path)<img src="{{ route('researchers.avatar',$person) }}" alt="">
@else{{ $person->initials() }}
@endif</span>
<h2>{{ mb_convert_case($person->name,MB_CASE_TITLE,'UTF-8') }}</h2>
<p>{{ $person->department?:config('catalogue.institution') }}</p><span class="small muted">{{ $person->publications_count }} publicaciones confirmadas</span></a>
@empty<div class="empty">
<h2>No se encontraron investigadores</h2>
<p>Prueba con otro apellido.</p></div>
@endforelse</div>
<div class="pagination">{{ $researchers->links() }}</div>
@endsection
