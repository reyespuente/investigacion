
@extends('layouts.app')
@section('title','Administración')
@section('content')<div class="page-heading">
<p class="eyebrow">ADMINISTRACIÓN</p>
<h1>Cuida el catálogo.</h1></div>
<nav class="account-nav"><a href="{{ route('admin.setup') }}">Asistente de instalación</a><a href="{{ route('admin.system') }}">Servicios y acceso</a><a href="{{ route('admin.settings') }}">Identidad y logotipos</a><a href="{{ route('admin.history') }}">Historial de cambios</a><a href="#usuarios">Cuentas de usuario</a><a href="{{ route('editor.create') }}">Agregar publicación</a></nav>
<div class="metrics">
@foreach(['review'=>'En revisión','incomplete'=>'Incompletas','published'=>'Publicadas','rejected'=>'Rechazadas'] as $key=>$label)<a href="{{ route('admin',['status'=>$key]) }}"><strong>{{ number_format($counts[$key]??0) }}</strong> {{ $label }}</a>
@endforeach</div>
<form method="get" class="form-grid admin-filter">
<label>Estado<select name="status">
@foreach(['review'=>'En revisión','incomplete'=>'Incompletas','published'=>'Publicadas','rejected'=>'Rechazadas'] as $key=>$label)<option value="{{ $key }}"
@selected(request('status','review')===$key)>{{ $label }}</option>
@endforeach</select></label>
<label>Buscar<input name="q" value="{{ request('q') }}"></label><button class="button secondary">Aplicar</button></form>
@forelse($publications as $publication)<article class="review-item">
<div class="publication-meta">#{{ $publication->id }} · {{ $publication->year }} · {{ $publication->type }}</div>
<h3>{{ $publication->title }}</h3>
<p>{{ $publication->venue }}</p><a href="{{ route('publications.show',$publication) }}">Revisar ficha</a> · <a href="{{ route('editor.edit',$publication) }}">Corregir metadatos</a><details><summary>Registrar decisión</summary>
<form method="post" action="{{ route('admin.review',$publication) }}">
@csrf
@include('partials.field',['name'=>'decision','label'=>'Decisión','type'=>'select','options'=>['published'=>'Publicar','review'=>'Mantener en revisión','rejected'=>'Rechazar']])
@include('partials.field',['name'=>'reason','label'=>'Motivo','type'=>'textarea','required'=>true])<button class="button secondary">Guardar decisión</button></form></details></article>
@empty<p>No hay referencias con estos filtros.</p>
@endforelse {{ $publications->links() }}<section class="article-section" id="usuarios">
<h2>Usuarios</h2>
<div class="table-scroll">
<table>
<thead>
<tr><th>Nombre</th><th>Correo privado</th><th>Rol</th><th>Acceso</th></tr></thead>
<tbody>
@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role==='admin'?'Administrador':'Investigador' }}</td><td>
<form method="post" action="{{ route('admin.user.toggle',$user) }}">
@csrf<button class="text-button">{{ $user->active?'Desactivar':'Activar' }}</button></form></td></tr>
@endforeach</tbody></table></div>{{ $users->links() }}<h2>Crear cuenta</h2>
<p class="muted">Puedes crear cuentas de cualquier dominio. La contraseña temporal deberá cambiarse al entrar.</p>
<form method="post" action="{{ route('admin.users') }}" class="form-panel">
@csrf
@include('partials.field',['name'=>'name','label'=>'Nombre','required'=>true])
@include('partials.field',['name'=>'email','label'=>'Correo electrónico','type'=>'email','required'=>true])
@include('partials.field',['name'=>'role','label'=>'Rol','type'=>'select','options'=>['member'=>'Investigador','admin'=>'Administrador']])<label>Perfil a asignar (opcional)<select name="researcher_id"><option value="">Sin perfil asignado</option>
@foreach($researchers as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>
@endforeach</select></label>
@include('partials.field',['name'=>'password','label'=>'Contraseña temporal','type'=>'password','required'=>true,'autocomplete'=>'new-password'])
@include('partials.field',['name'=>'password_confirmation','label'=>'Confirmar contraseña','type'=>'password','required'=>true,'autocomplete'=>'new-password'])<button class="button">Crear usuario</button></form></section>
@endsection
