
@extends('layouts.app')
@section('title','Contraseña')
@section('content')<div class="auth-shell">
<p class="eyebrow">SEGURIDAD DE TU CUENTA</p>
<h1>{{ $mode==='forgot'?'Recupera tu acceso':($mode==='reset'?'Elige tu nueva contraseña':'Cambia tu contraseña') }}</h1>
@if($mode==='change'&&auth()->user()->must_change_password)<p>Antes de continuar, reemplaza la contraseña temporal que recibiste.</p>
@endif<form method="post" action="{{ $mode==='forgot'?route('password.email'):($mode==='reset'?route('password.store'):route('password.update')) }}">
@csrf
@if($mode==='reset')<input type="hidden" name="token" value="{{ $token }}">
@endif
@if($mode!=='change')
@include('partials.field',['name'=>'email','label'=>'Correo de la cuenta','type'=>'email','required'=>true,'value'=>$email??'','autocomplete'=>'username'])
@else
@include('partials.field',['name'=>'current_password','label'=>'Contraseña actual','type'=>'password','required'=>true,'autocomplete'=>'current-password'])
@endif
@if($mode!=='forgot')
@include('partials.field',['name'=>'password','label'=>'Nueva contraseña','type'=>'password','required'=>true,'autocomplete'=>'new-password','help'=>'Al menos 12 caracteres, mayúsculas, minúsculas y números.'])
@include('partials.field',['name'=>'password_confirmation','label'=>'Confirmar contraseña','type'=>'password','required'=>true,'autocomplete'=>'new-password'])
@endif
@if($mode!=='change')
@include('partials.turnstile',['action'=>'reset'])
@endif<button class="button">{{ $mode==='forgot'?'Enviar enlace':'Guardar contraseña' }}</button></form></div>
@endsection
