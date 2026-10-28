
@extends('layouts.app')
@section('title','Editar perfil')
@section('content')<a class="back-link" href="{{ route('account') }}">Mi espacio</a>
<div class="page-heading">
<h1>Tu perfil, tu trayectoria.</h1>
<p class="muted">Elige qué información de contacto quieres compartir públicamente.</p></div>
<form class="form-panel" method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data">
@csrf
@include('partials.field',['name'=>'name','label'=>'Nombre público','value'=>$researcher->name,'required'=>true])
@include('partials.field',['name'=>'department','label'=>'Departamento o área','value'=>$researcher->department])
@include('partials.field',['name'=>'bio','label'=>'Acerca de ti','type'=>'textarea','value'=>$researcher->bio])
@include('partials.field',['name'=>'avatar','label'=>'Foto de perfil (JPG, PNG o WebP, hasta 3 MB)','type'=>'file'])<h2>Contacto público</h2>
<p class="small muted">Estos campos son opcionales. Tu correo de acceso permanece privado y no cambia con estos datos.</p>
@include('partials.field',['name'=>'contact_email','label'=>'Correo público','type'=>'email','value'=>$researcher->contacts['contact_email']??''])
@include('partials.field',['name'=>'website','label'=>'Sitio web','type'=>'url','value'=>$researcher->contacts['website']??''])
@include('partials.field',['name'=>'phone','label'=>'Teléfono público','value'=>$researcher->contacts['phone']??''])<button class="button">Guardar perfil</button></form>
@endsection
