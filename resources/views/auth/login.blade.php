
@extends('layouts.app')
@section('title','Iniciar sesión')
@section('content')<div class="auth-shell">
<p class="eyebrow">TU ESPACIO ACADÉMICO</p>
@if($target)
<h1>Agrega artículos a tu perfil</h1>
<p>Continúa con el correo institucional registrado para <strong>{{ $target->name }}</strong>. Al verificarlo, vincularemos este perfil a tu cuenta y abriremos el formulario.</p>
@else
<h1>Bienvenido a tu comunidad.</h1>
<p>Actualiza tu perfil, comparte publicaciones y reúne tu trayectoria.</p>
@endif
<a class="button full google-button" href="{{ route('auth.google', $target ? ['perfil' => $target->id] : []) }}"><i class="bi bi-google" aria-hidden="true"></i> Continuar con Google</a>
<p class="small muted">Registro con correo institucional verificado: {{ implode(', ',array_map(fn($d)=>'@'.$d,config('catalogue.domains'))) }}.</p>
@unless($target)
<details class="password-login"><summary>Acceder con correo y contraseña</summary>
<p class="small muted">Para cuentas creadas por un administrador.</p>
<form method="post" action="{{ route('login') }}">
@csrf
@include('partials.field',['name'=>'email','label'=>'Correo electrónico','type'=>'email','required'=>true,'autocomplete'=>'username'])
@include('partials.field',['name'=>'password','label'=>'Contraseña','type'=>'password','required'=>true,'autocomplete'=>'current-password'])<label class="checkbox"><input type="checkbox" name="remember" value="1"> Recordarme en este dispositivo</label>
@include('partials.turnstile',['action'=>'login'])<button class="button full">Iniciar sesión</button><a href="{{ route('password.request') }}">Olvidé mi contraseña</a></form></details>
@endunless
<p class="small muted">Consulta el <a href="{{ route('privacy') }}">aviso de privacidad</a> para conocer el tratamiento de tus datos.</p></div>
@endsection
