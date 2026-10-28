
@extends('layouts.app')
@section('title','Historial')
@section('content')<a href="{{ route('admin') }}">Administración</a>
<div class="page-heading">
<h1>Historial de cambios.</h1></div>
@foreach($events as $event)<article class="review-item">
<p class="small muted">{{ $event->created_at }} · {{ $event->actor?:'Importación del sistema' }}</p>
<h3>{{ $event->entity }} #{{ $event->entity_id }} · {{ $event->action }}</h3>
<p>{{ $event->reason }}</p><details><summary>Comparar versiones</summary>
<h4>Antes</h4><pre>{{ json_encode(json_decode($event->before??'null'),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
<h4>Después</h4><pre>{{ json_encode(json_decode($event->after??'null'),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></article>
@endforeach{{ $events->links() }}
@endsection
