@extends('layouts.app')
@section('title', $line->name)
@section('content')
<a class="back-link" href="{{ route('lines.index') }}">Líneas de investigación</a>
<div class="section-heading page-heading"><h1>{{ $line->name }}</h1>
    @if(auth()->user()?->role === 'admin')<a class="button secondary" href="{{ route('admin.lines.edit', $line) }}">Editar línea</a>
    @elseif(auth()->user()?->researcher)<a class="button secondary" href="{{ route('profile.edit') }}#research-lines">Administrar mi adscripción</a>@endif
</div>
<h2>Investigadores adscritos <span class="muted">{{ $researchers->total() }}</span></h2>
<div class="researcher-grid">
    @forelse($researchers as $person)<a class="researcher-card" href="{{ route('researchers.show', $person) }}"><span class="avatar large">{{ $person->initials() }}</span><h3>{{ $person->name }}</h3><p>{{ $person->department }}</p><span class="small muted">{{ $person->publications_count }} publicaciones confirmadas</span></a>
    @empty<p class="muted">Todavía no hay investigadores adscritos.</p>@endforelse
</div>
{{ $researchers->withQueryString()->links() }}
<section class="article-section"><h2>Proyectos vinculados <span class="muted">{{ $projects->total() }}</span></h2>
    <div class="project-grid">@forelse($projects as $project)@include('projects.card')@empty<p class="muted">Todavía no hay proyectos vinculados.</p>@endforelse</div>
    {{ $projects->withQueryString()->links() }}
</section>
@endsection
