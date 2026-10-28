
@extends('layouts.app')
@section('title','Identidad del portal')
@section('content')<a class="back-link" href="{{ route('admin') }}">Administración</a>
<div class="page-heading">
<p class="eyebrow">CONFIGURACIÓN</p>
<h1>Una identidad propia.</h1>
<p class="muted">Personaliza la presentación del repositorio de tu institución.</p></div>
<form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="form-panel">
@csrf
@foreach(['institution'=>'Nombre de la institución','short_name'=>'Nombre corto','portal_name'=>'Nombre del portal','network_name'=>'Red o institución superior (opcional)'] as $key=>$label)
@include('partials.field',['name'=>$key,'label'=>$label,'value'=>config('catalogue.'.$key),'required'=>$key!=='network_name'])
@endforeach<div class="form-grid">
@include('partials.field',['name'=>'primary_color','label'=>'Color primario','type'=>'color','value'=>config('catalogue.primary_color'),'required'=>true])
@include('partials.field',['name'=>'accent_color','label'=>'Color secundario','type'=>'color','value'=>config('catalogue.accent_color'),'required'=>true])</div>
<p class="small muted">Elige colores oscuros con contraste suficiente sobre blanco. Los enlaces y botones usarán estos colores.</p>
@include('partials.field',['name'=>'institution_logo','label'=>'Logotipo institucional','type'=>'file','help'=>'JPG, PNG o WebP, hasta 3 MB. Se conserva la proporción y se retiran los metadatos de la imagen.'])
@include('partials.field',['name'=>'network_logo','label'=>'Logotipo de la red o institución superior','type'=>'file'])<button class="button">Guardar identidad</button>
<p class="small muted">Los dominios y servicios se administran en Servicios y acceso.</p></form>
@endsection
