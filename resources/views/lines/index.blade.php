@extends('layouts.app')
@section('title', 'Líneas de investigación')
@section('content')
<div class="section-heading page-heading">
    <div><h1>Líneas de investigación</h1><p class="muted">Explora las áreas de trabajo y los investigadores adscritos.</p></div>
    @if(auth()->user()?->role === 'admin')<a class="button" href="{{ route('admin.lines.create') }}">Crear línea</a>
    @elseif(auth()->user()?->researcher)<a class="button secondary" href="{{ route('profile.edit') }}#research-lines">Elegir mis líneas</a>@endif
</div>
<form class="directory-search"><label class="search-box"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar línea de investigación" aria-label="Buscar línea de investigación"></label></form>
<p class="small muted">{{ $lines->total() }} líneas de investigación</p>
<div class="line-list">
    @forelse($lines as $line)<article class="line-list-row">
        <div><h2><a href="{{ route('lines.show', $line) }}">{{ $line->name }}</a></h2><p class="small muted">{{ $line->researchers_count }} investigadores · {{ $line->projects_count }} proyectos</p></div>
        <div class="button-row"><a href="{{ route('lines.show', $line) }}">Ver investigadores <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        @if(auth()->user()?->role === 'admin')<a href="{{ route('admin.lines.edit', $line) }}">Editar</a>@endif</div>
    </article>
    @empty<div class="empty"><h2>Aún no hay líneas de investigación</h2><p>El administrador puede registrar el catálogo institucional.</p></div>@endforelse
</div>
{{ $lines->links() }}
@endsection
