
@extends('layouts.app')
@section('title',$researcher->name)
@section('content')<a class="back-link" href="{{ route('researchers.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Investigadores</a>
<header class="profile-header"><span class="avatar profile-avatar">
@if($researcher->avatar_path)<img src="{{ route('researchers.avatar',$researcher) }}" alt="Foto de {{ $researcher->name }}">
@else{{ $researcher->initials() }}
@endif</span>
<div>
<p class="eyebrow">PERFIL ACADÉMICO</p>
<h1>{{ mb_convert_case($researcher->name,MB_CASE_TITLE,'UTF-8') }}</h1>
<p>{{ $researcher->department?:config('catalogue.institution') }}</p>
@if($researcher->orcid)<a class="orcid" href="{{ $researcher->orcid }}" rel="noopener noreferrer" target="_blank">ORCID <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endif</div>
<div class="profile-claim">
@if(!$researcher->user_id)
@auth<form method="post" action="{{ route('researchers.claim',$researcher) }}">
@csrf<button class="button secondary">Reclamar perfil</button></form>
@else<a class="button secondary" href="{{ route('login') }}">Reclamar perfil</a>
@endauth<p class="small muted">Solo puede reclamarlo su titular con el correo institucional del padrón.</p>
@elseif(auth()->id()===$researcher->user_id)<a class="button secondary" href="{{ route('profile.edit') }}">Editar mi perfil</a>
@else<span class="badge">Perfil reclamado</span>
@endif</div></header>
@if($researcher->bio)<p class="long-text profile-bio">{{ $researcher->bio }}</p>
@endif
@if($researcher->contacts)<div class="tags">
@foreach($researcher->contacts as $key=>$value)
@if($key==='contact_email')<a href="mailto:{{ $value }}">{{ $value }}</a>
@elseif($key==='website')<a href="{{ $value }}" rel="noopener noreferrer" target="_blank">Sitio web <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@else<span>{{ $value }}</span>
@endif
@endforeach</div>
@endif <div class="section-heading">
<h2>Publicaciones <span class="muted">{{ $publications->total() }}</span></h2><a href="{{ route('publications.index',['researcher'=>$researcher->id]) }}">Explorar y exportar <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
@include('partials.results')
@if($documents->isNotEmpty())<section class="article-section">
<h2>Documentos y certificaciones</h2>
@foreach($documents as $document)<a class="document-link" href="{{ route('documents.show',$document) }}"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>{{ $document->title }}</a>
@endforeach</section>
@endif
@endsection
