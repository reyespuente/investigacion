@extends('layouts.app')
@section('title', 'Administrar correos')
@section('content')
<a class="back-link" href="{{ route('researchers.show', $researcher) }}">Volver al perfil público</a>
<div class="page-heading">
    <h1>Correos de {{ $researcher->name }}</h1>
    <p class="muted">Estas direcciones aparecerán en el perfil público. El acceso y la vinculación siguen usando el correo del padrón.</p>
</div>
<form class="form-panel" method="post" action="{{ route('admin.researchers.emails.update', $researcher) }}">
    @csrf
    @include('account.public-emails')
    <button class="button">Guardar correos</button>
</form>
@endsection
