
@extends('layouts.app')
@section('title',$publication->title)
@section('content')
<a class="back-link" href="{{ route('publications.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Explorar publicaciones</a>
<div class="detail-grid">
<article>
<div class="publication-meta">{{ $publication->label() }} <span>·</span> {{ $publication->year }} <span>·</span> {{ $publication->origin==='researcher'?'Aportación del investigador':($publication->origin==='administrator'?'Edición administrativa':'Catálogo consolidado') }}</div>
<h1 class="article-title">{{ $publication->title }}</h1>
<p class="venue large">{{ $publication->venue }}</p>
<div class="author-list">
@foreach($publication->authors as $author)<div><strong>{{ $author['nombre'] }}</strong>
@if(!empty($author['orcid']))<a href="{{ $author['orcid'] }}" rel="noopener noreferrer" target="_blank" class="orcid">ORCID <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endif
@if(!empty($author['afiliaciones']))<small>{{ implode('; ',$author['afiliaciones']) }}</small>
@endif</div>
@endforeach</div>
@if($publication->researchers->isNotEmpty())<div class="tags">
@foreach($publication->researchers as $person)<a href="{{ route('researchers.show',$person) }}"><i class="bi bi-person" aria-hidden="true"></i> {{ $person->name }}</a>
@endforeach</div>
@endif<section class="article-section">
<h2>Resumen</h2>
<p class="long-text">{{ $publication->abstract?:'Resumen no disponible en las fuentes recuperadas.' }}</p>
@if(count($publication->metadata['resumenes']??[])>1)<details><summary>Otros idiomas y versiones</summary>
@foreach(array_slice($publication->metadata['resumenes'],1) as $summary)<h3>{{ $summary['idioma']==='und'?'Idioma no declarado':$summary['idioma'] }}</h3>
<p class="long-text">{{ $summary['texto'] }}</p>
@endforeach</details>
@endif</section>
<section class="article-section">
<h2>Datos de publicación</h2>
<dl class="metadata-grid">
@foreach(['volumen'=>'Volumen','numero'=>'Número','paginas'=>'Páginas','issn'=>'ISSN','isbn'=>'ISBN','editorial'=>'Editorial','idioma'=>'Idioma','numero_articulo'=>'Número de artículo'] as $key=>$label)
@if(!empty($publication->metadata['bibliografia'][$key]))<div><dt>{{ $label }}</dt><dd>{{ is_array($publication->metadata['bibliografia'][$key])?implode(', ',$publication->metadata['bibliografia'][$key]):$publication->metadata['bibliografia'][$key] }}</dd></div>
@endif
@endforeach
@if($publication->doi)<div><dt>DOI</dt><dd>{{ $publication->doi }}</dd></div>
@endif
@if($publication->published_date)<div><dt>Fecha declarada</dt><dd>{{ $publication->published_date }}</dd></div>
@endif</dl>
@if(!empty($publication->metadata['bibliografia']['palabras_clave']))<div class="tags">
@foreach(array_slice($publication->metadata['bibliografia']['palabras_clave'],0,15) as $keyword)<a href="{{ route('publications.index',['q'=>$keyword]) }}">{{ $keyword }}</a>
@endforeach</div>
@endif</section>
<section class="article-section" id="citar">
<h2>Cita esta publicación</h2>
<p class="muted small">Comprueba el estilo que requiere tu institución o revista.</p>
<div class="citation-controls">
<label class="sr-only" for="citation-style">Estilo de cita</label><select id="citation-style"><option value="apa">APA 7</option><option value="chicago">Chicago · autor-fecha</option><option value="vancouver">Vancouver</option><option value="ieee">IEEE</option><option value="bibtex">BibTeX</option><option value="ris">RIS</option></select><button class="button secondary" type="button" data-copy-citation><i class="bi bi-copy" aria-hidden="true"></i> Copiar cita</button><span id="citation-status" role="status"></span></div>
<div id="citation-text" class="citation-box">{{ $publication->metadata['referencia']??$publication->title }}</div><script type="application/json" id="citation-data">{!! json_encode($publication->csl(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) !!}</script></section>
<section class="article-section">
<h2>Fuentes y trazabilidad</h2>
<p class="muted small">Se conserva la procedencia de los datos y se da prioridad a la revista cuando existe información editorial.</p>
<div class="source-list">
@foreach(collect($publication->metadata['procedencia']??[])->filter(fn($p)=>!empty($p['url']))->unique('url') as $source)<a href="{{ $source['url'] }}" rel="noopener noreferrer" target="_blank">{{ $source['fuente']??'Fuente' }} <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endforeach</div>
@if(!empty($publication->metadata['bibliografia']['licencias']))<details><summary>Licencias declaradas por las fuentes</summary>
@foreach($publication->metadata['bibliografia']['licencias'] as $license)<p>{{ $license['rights']??$license['URL']??$license['rightsUri']??'Licencia en el registro de origen' }}</p>
@endforeach</details>
@endif</section>
@if(auth()->user()?->role==='admin')
<section class="article-section"><h2>Revisar atribución a un perfil</h2><p class="small muted">Confirma la identidad con evidencias del trabajo. El nombre parecido no basta.</p>
@foreach($publication->researchers()->get() as $linked)<p class="small">{{ $linked->name }} · {{ $linked->pivot->state }}</p>@endforeach
<form method="post" action="{{ route('admin.attribution',$publication) }}" class="form-panel">@csrf<label>Perfil<select name="researcher_id">@foreach(\App\Models\Researcher::orderBy('name')->get() as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label><label>Decisión<select name="state"><option value="pending">Pendiente</option><option value="confirmed">Confirmada</option><option value="rejected">Rechazada</option></select></label><label>Motivo y evidencia<textarea name="reason" required></textarea></label><button class="button secondary">Guardar atribución</button></form></section>
@endif
</article>
<aside class="detail-actions">
<div class="action-panel">
<p class="eyebrow">ACCEDE AL TRABAJO</p><a class="button full" href="{{ $publication->external_url }}" rel="noopener noreferrer" target="_blank">Ir a la publicación <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
<p class="small muted">Te dirigiremos al sitio que publica o aloja el trabajo.</p><a class="button secondary full" href="#citar"><i class="bi bi-quote" aria-hidden="true"></i> Copiar una cita</a><button class="button secondary full" type="button" data-export-open><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Descargar ficha PDF</button>
@can('update',$publication)<a class="text-link" href="{{ route('editor.edit',$publication) }}">Editar publicación</a>
@endcan</div>
@if($publication->documents->isNotEmpty())<section class="action-panel">
<h2>Documentos compartidos</h2>
@foreach($publication->documents as $document)<a class="document-link" href="{{ route('documents.show',$document) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> {{ $document->title }}</a>
@endforeach<p class="small muted">Archivos aportados por investigadores con declaración de derechos.</p></section>
@endif</aside></div>
<dialog id="export-dialog"><button class="dialog-close" data-close-dialog type="button" aria-label="Cerrar"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
<h2>Descargar ficha bibliográfica</h2>
<p>El PDF contiene los metadatos y el enlace al trabajo original.</p>
<form method="post" action="{{ route('publications.pdf',$publication) }}" data-download-form>
@csrf
@include('partials.turnstile',['action'=>'export'])<button class="button">Descargar ficha</button></form></dialog>
@endsection
