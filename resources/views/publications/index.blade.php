
@extends('layouts.app')
@section('title','Explorar publicaciones')
@section('content')
<div class="page-heading">
<p class="eyebrow">EL CATÁLOGO DE INVESTIGACIÓN</p>
<h1>Encuentra tu próxima idea.</h1>
<p class="muted">Publicaciones, referencias y acceso a sus fuentes originales.</p></div>
<div class="browse-grid">
<aside class="filters">
<h2>Filtrar resultados</h2>
<form method="get" data-live-search>
<label class="search-box small-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Título, autor, DOI…" aria-label="Buscar en el catálogo"></label>
<label>Año<select name="year"><option value="">Todos los años</option>
@foreach($years as $year)<option
@selected(request('year')==$year)>{{ $year }}</option>
@endforeach</select></label>
<label>Tipo de publicación<select name="type"><option value="">Todos los tipos</option>
@foreach(config('catalogue.types') as $k=>$v)<option value="{{ $k }}"
@selected(request('type')==$k)>{{ $v }}</option>
@endforeach</select></label>
<label>Ordenar por<select name="sort"><option value="recent"
@selected(request('sort','recent')==='recent')>Más recientes</option><option value="oldest"
@selected(request('sort')==='oldest')>Más antiguas</option><option value="title"
@selected(request('sort')==='title')>Título</option></select></label>
<label class="checkbox"><input name="abstract" type="checkbox" value="1"
@checked(request('abstract'))> Solo con resumen</label>
@if(request('researcher'))<input name="researcher" type="hidden" value="{{ (int)request('researcher') }}">
@endif<a class="text-link" href="{{ route('publications.index') }}">Limpiar filtros</a></form><button class="button secondary full" data-export-open type="button"><i class="bi bi-download" aria-hidden="true"></i> Exportar resultados</button>
<p class="small muted">Exporta la selección actual, hasta 5,000 referencias.</p></aside>
<section>
<div id="search-error" role="alert" class="notice error" hidden>No pudimos actualizar los resultados. Intenta nuevamente.</div>
<div id="results" aria-live="polite">
@include('partials.results')</div></section></div>
<dialog id="export-dialog"><button class="dialog-close" type="button" data-close-dialog aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
<h2>Exportar resultados</h2>
<p>Incluye las referencias de tu búsqueda y sus enlaces.</p>
<form action="{{ route('exports') }}" method="post" data-export-form>
@csrf<div data-export-filters></div>
<label>Formato<select name="format"><option value="csv">CSV · hoja de cálculo</option><option value="xlsx">Excel · XLSX</option><option value="json">JSON · datos estructurados</option><option value="xml">XML</option><option value="bib">BibTeX · LaTeX</option><option value="ris">RIS · Zotero, Mendeley y EndNote</option></select></label>
@include('partials.turnstile',['action'=>'export'])<button class="button" type="submit">Descargar archivo</button></form></dialog>
@endsection
