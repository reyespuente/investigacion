@extends('layouts.app')
@section('title', 'Proyectos de investigación')
@section('content')
<div class="section-heading page-heading">
    <div><h1>Proyectos de investigación</h1><p class="muted">Conoce los proyectos, sus participantes y sus líneas de investigación.</p></div>
    @if(auth()->user()?->role === 'admin')<a class="button" href="{{ route('admin.projects.create') }}">Crear proyecto</a>@endif
</div>
<form class="directory-search project-search" method="get">
    <label class="field"><span>Buscar proyecto</span><input type="search" name="q" value="{{ request('q') }}" placeholder="Nombre o descripción"></label>
    <label class="field"><span>Línea de investigación</span><select name="line"><option value="">Todas</option>@foreach($lines as $line)<option value="{{ $line->id }}" @selected(request('line') == $line->id)>{{ $line->label() }}</option>@endforeach</select></label>
    <button class="button secondary">Filtrar</button>
</form>
<p class="small muted">{{ $projects->total() }} proyectos</p>
<div class="project-grid">
    @forelse($projects as $project)@include('projects.card')
    @empty<div class="empty"><h2>No hay proyectos en esta selección</h2><p>Los proyectos que registre el administrador aparecerán aquí.</p></div>@endforelse
</div>
{{ $projects->links() }}
@endsection
