@extends('layouts.app')
@section('title', $line->exists ? 'Editar línea' : 'Crear línea')
@section('content')
<a class="back-link" href="{{ route('admin.lines') }}">Líneas de investigación</a>
<div class="page-heading"><h1>{{ $line->exists ? 'Editar línea de investigación' : 'Crear línea de investigación' }}</h1></div>
<form class="form-panel" method="post" action="{{ $line->exists ? route('admin.lines.update', $line) : route('admin.lines.store') }}">
    @csrf
    @include('partials.field', ['name' => 'name', 'label' => 'Nombre de la línea', 'value' => $line->name, 'required' => true])
    @include('partials.field', ['name' => 'program', 'label' => 'Programa académico', 'value' => $line->program])
    @include('partials.field', ['name' => 'code', 'label' => 'Clave oficial (LGAC)', 'value' => $line->code])
    <button class="button">Guardar línea</button>
</form>
@if($line->exists)
    <form class="form-panel" method="post" action="{{ route('admin.lines.destroy', $line) }}">
        @csrf
        @method('DELETE')
        <h2>Eliminar línea</h2>
        <p>Se quitará del catálogo y se desvinculará de {{ $line->researchers_count }} investigadores y {{ $line->projects_count }} proyectos. No se eliminarán los perfiles ni los proyectos.</p>
        <label class="checkbox"><input type="checkbox" name="confirm_delete" value="1" required> Confirmo que deseo eliminar esta línea.</label>
        <button class="button secondary">Eliminar línea</button>
    </form>
@endif
@endsection
