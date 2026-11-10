
@extends('layouts.app')
@section('title','Mi espacio')
@section('content')<div class="section-heading page-heading">
<div>
<h1>{{ $researcher ? 'Mis publicaciones' : 'Mi espacio' }}</h1>
<p class="muted">{{ $researcher?->name ?? auth()->user()->name }}</p></div>
<form method="post" action="{{ route('logout') }}">
@csrf<button class="button secondary">Cerrar sesión</button></form></div>
<nav class="account-nav" aria-label="Mi cuenta">
@if($researcher)<a class="button" href="{{ route('editor.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar artículos</a><a href="{{ route('profile.edit') }}">Editar perfil y correos</a><a href="{{ route('researchers.show',$researcher) }}">Ver perfil público</a>
@endif<a href="{{ route('password.change') }}">Cambiar contraseña</a>
@if(auth()->user()->role==='admin')<a href="{{ route('admin') }}">Administración</a>
@endif</nav>
@if(!$researcher)
@if($suggestedProfile)
<article class="account-publication">
<div><h2><a href="{{ route('researchers.show',$suggestedProfile) }}">{{ $suggestedProfile->name }}</a></h2>
<p class="muted">Tu correo institucional coincide con este perfil.</p></div>
<form method="post" action="{{ route('researchers.claim',$suggestedProfile) }}">
@csrf<button class="button">Vincular y agregar artículos</button></form>
</article>
@else
<div class="empty">
<h2>Vincula tu perfil para agregar artículos</h2>
<p>Busca tu nombre en el directorio. Si tu correo institucional cambió o tu perfil no aparece, contacta al administrador.</p>
<a class="button" href="{{ route('researchers.index') }}">Buscar mi perfil</a>
</div>
@endif
@else
<nav class="status-tabs" aria-label="Mis publicaciones">
<a href="{{ route('account',['status'=>'pending']) }}" @class(['active'=>$status==='pending']) @if($status==='pending') aria-current="page" @endif>Por revisar <strong>{{ $counts['pending'] }}</strong></a>
<a href="{{ route('account',['status'=>'published']) }}" @class(['active'=>$status==='published']) @if($status==='published') aria-current="page" @endif>Publicadas <strong>{{ $counts['published'] }}</strong></a>
<a href="{{ route('account',['status'=>'all']) }}" @class(['active'=>$status==='all']) @if($status==='all') aria-current="page" @endif>Todas</a>
</nav>
@forelse($publications as $publication)<article class="account-publication">
<div><span class="badge">{{ $publication->pivot->state==='pending'?'Autoría por confirmar':($publication->status==='published'?'Publicada':'Pendiente de revisión') }}</span>
<h3>{{ $publication->title }}</h3>
<p class="small muted">{{ $publication->year }} · {{ $publication->venue }}</p></div>
<div class="button-row">
@can('update',$publication)<a href="{{ route('editor.edit',$publication) }}">Editar</a>
@endcan
@if(in_array($publication->status,['review','incomplete']) || $publication->pivot->state==='pending')<a class="button small-button" href="{{ route('account.review',$publication) }}">Revisar y publicar <i class="bi bi-arrow-right" aria-hidden="true"></i></a>@endif
@if($publication->status==='published')<a href="{{ route('publications.show',$publication) }}">Ver ficha</a>
@endif</div></article>
@empty<div class="empty"><h3>{{ $status==='pending'?'Estás al día':'Todavía no hay artículos en esta lista' }}</h3><a href="{{ $status==='pending'?route('account',['status'=>'published']):route('editor.create') }}">{{ $status==='pending'?'Ver artículos publicados':'Agregar un artículo' }}</a></div>@endforelse {{ $publications->links() }}<details class="review-evidence">
<summary>Compartir un documento</summary>
<p>Puedes publicar un PDF del que tengas derechos, una certificación u otro documento académico.</p>
@include('account.upload')</details>
<section class="article-section">
<h2>Mis documentos</h2>
@foreach($researcher->documents as $document)<div class="account-publication"><a href="{{ route('documents.show',$document) }}">{{ $document->title }}</a><span>{{ $document->is_public?'Público':'Privado' }}</span>
@if($document->is_public)<form method="post" action="{{ route('documents.hide',$document) }}">
@csrf<button class="text-button">Dejar de publicar</button></form>
@endif</div>
@endforeach</section>
@endif
@endsection
