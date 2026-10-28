
@extends('layouts.app')
@section('title',$document->title)
@section('content')<a class="back-link" href="{{ route('researchers.show',$document->researcher) }}">Perfil del investigador</a>
<div class="page-heading">
<p class="eyebrow">DOCUMENTO COMPARTIDO POR SU TITULAR</p>
<h1>{{ $document->title }}</h1>
<p class="muted">{{ $document->researcher->name }} · {{ number_format($document->size/1024/1024,1) }} MB · {{ $document->is_public?'Público':'Privado' }}</p></div>
<div class="pdf-toolbar"><button class="button secondary" data-pdf-prev>Anterior</button><span id="pdf-status" role="status">Cargando documento…</span><button class="button secondary" data-pdf-next>Siguiente</button><a class="text-link" href="{{ route('documents.stream',$document) }}" target="_blank" rel="noopener noreferrer">Abrir archivo original <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
<div class="pdf-viewer" data-pdf-src="{{ route('documents.stream',$document) }}"><canvas id="pdf-canvas" aria-label="Página del documento PDF"></canvas></div>
<p class="small muted">El archivo fue aportado por un investigador con declaración de derechos. Su contenido y licencia corresponden al titular.</p>
@endsection
