
@php
$institutionLogo=config('catalogue.institution_logo_path')?route('branding.logo',['kind'=>'institution','v'=>config('catalogue.logo_version')]):(config('catalogue.institution_logo')?asset(config('catalogue.institution_logo')):null);
$networkLogo=config('catalogue.network_logo_path')?route('branding.logo',['kind'=>'network','v'=>config('catalogue.logo_version')]):(config('catalogue.network_logo')?asset(config('catalogue.network_logo')):null);
@endphp
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="description" content="Publicaciones, investigadores, referencias y acceso a las fuentes originales."><title>
@yield('title',config('catalogue.portal_name')) · {{ config('catalogue.institution') }}</title>
@if($institutionLogo)<link rel="icon" href="{{ $institutionLogo }}">
@endif
@vite(['resources/css/app.css','resources/js/app.js'])
@if(config('catalogue.turnstile_enabled'))<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif</head><body style="--brand-primary:{{ config('catalogue.primary_color') }};--brand-accent:{{ config('catalogue.accent_color') }}"><a class="skip" href="#contenido">Saltar al contenido</a>
<div class="institutional">
<div class="container">{{ config('catalogue.institution') }} <span>{{ config('catalogue.network_name') }}</span></div></div>
<header class="header">
<div class="container header-inner"><a class="brand" href="{{ route('home') }}">
@if($institutionLogo)<img src="{{ $institutionLogo }}" width="42" height="48" alt="{{ config('catalogue.short_name') }}">
@endif<span>{{ config('catalogue.portal_name') }}</span></a>
<nav aria-label="Navegación principal"><a
@class(['active'=>request()->routeIs('publications.*')]) href="{{ route('publications.index') }}">Publicaciones</a><a
@class(['active'=>request()->routeIs('researchers.*')]) href="{{ route('researchers.index') }}">Investigadores</a><a
@class(['active'=>request()->routeIs('statistics')]) href="{{ route('statistics') }}">Estadísticas</a></nav>
<div class="header-actions">
@auth<a class="button secondary" href="{{ route('account') }}"><i class="bi bi-person" aria-hidden="true"></i> Mi espacio</a>
@else<a class="button" href="{{ route('login') }}">Iniciar sesión <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
@endauth
@if($networkLogo)<img class="tecnm" src="{{ $networkLogo }}" alt="{{ config('catalogue.network_name') }}" width="140" height="66">
@endif</div></div></header>
<main id="contenido" class="container main">
@if(session('success'))<div role="status" class="notice success">{{ session('success') }}</div>
@endif
@if($errors->any())<div role="alert" class="notice error"><strong>Revisa los siguientes datos:</strong><ul>
@foreach($errors->all() as $error)<li>{{ $error }}</li>
@endforeach</ul></div>
@endif
@yield('content')</main>
<footer class="footer">
<div class="container footer-inner"><span class="credit">Reyes Puente</span><a class="github" href="https://github.com/reyespuente/investigacion" target="_blank" rel="noopener noreferrer" aria-label="Código fuente en GitHub" title="Código fuente en GitHub"><i class="bi bi-github" aria-hidden="true"></i></a><span class="footer-spacer"></span><a href="{{ route('privacy') }}">Privacidad</a><a href="{{ route('cookies') }}">Cookies</a><button class="text-button" type="button" data-cookie-open>Preferencias de cookies</button></div></footer>
<section class="cookie-banner" id="cookie-banner" aria-label="Preferencias de cookies"
@if(request()->cookie('cookie_preferences')) hidden
@endif>
<div class="container cookie-inner">
<p>Usamos cookies necesarias para proteger tu sesión. Tú decides sobre las opcionales. <a href="{{ route('cookies') }}">Más información</a></p>
<div class="button-row"><button class="button secondary" data-cookie-choice="essential" type="button">Rechazar opcionales</button><button class="button" data-cookie-choice="all" type="button">Aceptar opcionales</button></div>
<p data-cookie-error role="alert" hidden>No se pudo guardar la preferencia. Intenta nuevamente.</p></div></section></body></html>
