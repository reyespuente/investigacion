@extends('layouts.app')
@section('title', 'Eliminar de mi bandeja')
@section('content')
<a class="back-link" href="{{ route('account') }}">Mis publicaciones</a>
<div class="page-heading">
    <h1>Eliminar de mi bandeja</h1>
    <p class="muted">{{ $publication->year }} · {{ $publication->venue }}</p>
</div>
@include('account.dismiss-publication', ['open' => true])
@endsection
