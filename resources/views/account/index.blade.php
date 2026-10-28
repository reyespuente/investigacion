
@extends('layouts.app')
@section('title','Mi espacio')
@section('content')<div class="section-heading page-heading">
<div>
<p class="eyebrow">MI ESPACIO</p>
<h1>Hola, {{ Str::before(auth()->user()->name,' ') }}.</h1></div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="button secondary">Cerrar sesión</button></form></div>
<nav class="account-nav" aria-label="Mi cuenta">
@if($researcher)<a href="{{ route('profile.edit') }}">Editar perfil</a><a href="{{ route('researchers.show',$researcher) }}">Ver perfil público</a><a class="button" href="{{ route('editor.create') }}">Agregar publicación</a>
@endif<a href="{{ route('password.change') }}">Cambiar contraseña</a>
@if(auth()->user()->role==='admin')<a href="{{ route('admin') }}">Administración</a>
@endif</nav>
@if(!$researcher)<div class="empty">
<h2>Tu cuenta ya está lista.</h2>
<p>Encuentra tu perfil y pulsa «Reclamar perfil». El correo de Google debe coincidir con el registrado en el padrón.</p><a class="button" href="{{ route('researchers.index') }}">Encontrar mi perfil</a>
<p class="small muted">Si tu perfil no aparece o tu correo cambió, solicita ayuda al administrador.</p></div>
@else<h2>Mis publicaciones</h2>
<p class="muted">Las publicaciones que agregues o edites se publican directamente. Todas las modificaciones conservan un historial.</p>
@foreach($publications as $publication)<article class="account-publication">
<div><span class="badge">{{ $publication->status==='published'?'Publicada':($publication->pivot->state==='pending'?'Autoría por confirmar':'Pendiente de revisión') }}</span>
<h3>{{ $publication->title }}</h3>
<p class="small muted">{{ $publication->year }} · {{ $publication->venue }}</p></div>
<div class="button-row">
@can('update',$publication)<a href="{{ route('editor.edit',$publication) }}">Editar</a>
@endcan
@if($publication->pivot->state==='pending')<form method="post" action="{{ route('editor.confirm',$publication) }}">
@csrf<label class="checkbox"><input type="checkbox" name="authorship" required> Confirmo que soy autor/a</label><button class="button secondary small-button">Confirmar autoría</button></form>
@endif
@if($publication->status==='published')<a href="{{ route('publications.show',$publication) }}">Ver ficha</a>
@endif</div></article>
@endforeach {{ $publications->links() }}<section class="article-section">
<h2>Compartir un documento</h2>
<p>Puedes publicar un PDF del que tengas derechos, una certificación u otro documento académico.</p>
@include('account.upload')</section>
<section class="article-section">
<h2>Mis documentos</h2>
@foreach($researcher->documents as $document)<div class="account-publication"><a href="{{ route('documents.show',$document) }}">{{ $document->title }}</a><span>{{ $document->is_public?'Público':'Privado' }}</span>
@if($document->is_public)<form method="post" action="{{ route('documents.hide',$document) }}">
@csrf<button class="text-button">Dejar de publicar</button></form>
@endif</div>
@endforeach</section>
@endif
@endsection
