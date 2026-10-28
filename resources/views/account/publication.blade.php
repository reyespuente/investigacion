
@extends('layouts.app')
@section('title','Editar publicación')
@section('content')<a class="back-link" href="{{ route('account') }}">Mi espacio</a>
<div class="page-heading">
<h1>{{ $publication->exists?'Editar publicación':'Comparte tu trabajo.' }}</h1>
<p class="muted">Guarda una referencia completa y enlaza al trabajo original.</p></div>
<form class="form-panel wide-form" method="post" action="{{ $publication->exists?route('editor.update',$publication):route('editor.store') }}">
@csrf<input type="hidden" name="version" value="{{ $publication->revision??0 }}">
@include('partials.field',['name'=>'title','label'=>'Título','value'=>$publication->title,'required'=>true])<div class="form-grid">
@include('partials.field',['name'=>'year','label'=>'Año','type'=>'number','value'=>$publication->year,'required'=>true])
@include('partials.field',['name'=>'published_date','label'=>'Fecha completa (si se conoce)','type'=>'date','value'=>strlen($publication->published_date??'')===10?$publication->published_date:''])
@include('partials.field',['name'=>'type','label'=>'Tipo','type'=>'select','options'=>config('catalogue.types'),'value'=>$publication->type,'required'=>true])
@include('partials.field',['name'=>'doi','label'=>'DOI (opcional)','value'=>$publication->doi,'help'=>'Solo el identificador, por ejemplo 10.1234/articulo.'])</div>
@include('partials.field',['name'=>'venue','label'=>'Revista, congreso o medio de publicación','value'=>$publication->venue,'required'=>true])
@include('partials.field',['name'=>'external_url','label'=>'Enlace al trabajo original','type'=>'url','value'=>$publication->external_url,'required'=>true])
@include('partials.field',['name'=>'authors_text','label'=>'Autores en orden','type'=>'textarea','rows'=>7,'value'=>collect($publication->authors)->map(fn($a)=>$a['nombre'].(!empty($a['afiliaciones'])?' | '.implode('; ',$a['afiliaciones']):''))->implode("\n"),'required'=>true,'help'=>'Un autor por línea. Puedes agregar su afiliación después de |. Conserva el orden publicado.'])
@include('partials.field',['name'=>'abstract','label'=>'Resumen','type'=>'textarea','rows'=>8,'value'=>$publication->abstract])<div class="form-grid">
@include('partials.field',['name'=>'volume','label'=>'Volumen','value'=>$publication->metadata['bibliografia']['volumen']??''])
@include('partials.field',['name'=>'issue','label'=>'Número','value'=>$publication->metadata['bibliografia']['numero']??''])
@include('partials.field',['name'=>'pages','label'=>'Páginas','value'=>$publication->metadata['bibliografia']['paginas']??''])</div>
<p class="small muted">Se conservará un historial de los cambios. Si el trabajo tiene otros investigadores vinculados, la edición actualizará la ficha compartida.</p><button class="button">Guardar y publicar</button></form>
@endsection
