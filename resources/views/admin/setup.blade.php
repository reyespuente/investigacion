@extends('layouts.app')
@section('title','Asistente de instalación')
@section('content')
<a class="back-link" href="{{ route('admin') }}">Administración</a>
<div class="page-heading"><p class="eyebrow">PRIMEROS PASOS</p><h1>Prepara tu repositorio.</h1><p class="muted">Un recorrido por los ajustes de tu institución. Puedes volver a este asistente cuando cambien tus servicios.</p></div>
<div class="setup-steps"><a class="setup-step" href="{{ route('admin.settings') }}"><span class="avatar">1</span><div><h2>Identidad</h2><p>Nombre, colores y logotipos.</p></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a class="setup-step" href="{{ route('admin.system') }}"><span class="avatar">2</span><div><h2>Servicios y acceso</h2><p>Dominio, registro, correo, almacenamiento y autenticación.</p></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><a class="setup-step" href="{{ route('admin') }}"><span class="avatar">3</span><div><h2>Catálogo</h2><p>Importa tus datos privados y revisa las referencias.</p></div><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
<section class="article-section"><h2>Estado de la instalación</h2><p class="small muted">Esta revisión comprueba la configuración disponible. Usa las pruebas de correo y almacenamiento para comprobar conexiones reales.</p>
@foreach($checks as $check)
<div class="setup-check"><i class="bi {{ $check['ready']?'bi-check-circle':'bi-circle' }}" aria-hidden="true"></i><div><h3>{{ $check['name'] }}</h3><p>{{ $check['detail'] }}</p></div><span class="badge">{{ $check['ready']?'Configurado':'Por completar' }}</span></div>
@endforeach
</section><section class="article-section"><h2>Antes de abrirlo al público</h2><p>Comprueba el acceso con una cuenta institucional, una exportación protegida, un documento público y otro privado. Revisa el aviso de privacidad, el remitente de correo y la política de respaldos de la institución.</p><a class="button" href="{{ route('admin.system') }}">Configurar servicios</a></section>
@endsection
