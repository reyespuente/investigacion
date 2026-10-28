@extends('layouts.app')
@section('title','Revisar mi publicación')
@section('content')
<a class="back-link" href="{{ route('account',['status'=>'pending']) }}">Volver a mis pendientes</a>
<div class="workspace-heading"><div><p class="eyebrow">MI TRAYECTORIA</p><h1>Revisar y publicar</h1><p class="muted">Confirma tu participación y publica esta referencia sin esperar al administrador.</p></div></div>
<section class="researcher-review">@include('partials.review',['administrator'=>false])</section>
@endsection
