@extends('layouts.app')
@section('title', $line->exists ? 'Editar línea' : 'Crear línea')
@section('content')
<a class="back-link" href="{{ route('admin.lines') }}">Líneas de investigación</a>
<div class="page-heading"><h1>{{ $line->exists ? 'Editar línea de investigación' : 'Crear línea de investigación' }}</h1></div>
<form class="form-panel" method="post" action="{{ $line->exists ? route('admin.lines.update', $line) : route('admin.lines.store') }}">
    @csrf
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre de la línea', 'value' => $line->name, 'required' => true])
    <button class="button">Guardar línea</button>
</form>
@endsection
