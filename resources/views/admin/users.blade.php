@extends('layouts.app')
@section('title','Usuarios')
@section('content')
<div class="workspace-heading"><div><p class="eyebrow">ACCESO Y PERFILES</p><h1>Usuarios</h1><p class="muted">Cuentas del portal y asignación de perfiles.</p></div><a class="button" href="#crear-usuario">Crear usuario</a></div>
<div class="table-scroll"><table><thead><tr><th>Nombre</th><th>Correo privado</th><th>Rol</th><th>Acceso</th></tr></thead><tbody>
@foreach($users as $user)<tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->role==='admin'?'Administrador':'Investigador' }}</td><td><form method="post" action="{{ route('admin.user.toggle',$user) }}">@csrf<button class="button secondary small-button">{{ $user->active?'Desactivar':'Activar' }}</button></form></td></tr>@endforeach
</tbody></table></div>{{ $users->links() }}
<section id="crear-usuario"><h2>Crear cuenta</h2><p class="muted">Puede pertenecer a cualquier dominio. Deberá cambiar su contraseña temporal al entrar.</p><form method="post" action="{{ route('admin.users') }}" class="form-panel">@csrf
@include('partials.field',['name'=>'name','label'=>'Nombre','required'=>true])
@include('partials.field',['name'=>'email','label'=>'Correo electrónico','type'=>'email','required'=>true])
@include('partials.field',['name'=>'role','label'=>'Rol','type'=>'select','options'=>['member'=>'Investigador','admin'=>'Administrador']])
<label>Perfil a asignar<select name="researcher_id"><option value="">Sin asignar</option>@foreach($researchers as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
@include('partials.field',['name'=>'password','label'=>'Contraseña temporal','type'=>'password','required'=>true,'autocomplete'=>'new-password'])
@include('partials.field',['name'=>'password_confirmation','label'=>'Confirmar contraseña','type'=>'password','required'=>true,'autocomplete'=>'new-password'])
<button class="button">Crear usuario</button></form></section>
@endsection
