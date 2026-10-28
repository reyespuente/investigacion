
@extends('layouts.app')
@section('title','Política de cookies')
@section('content')<article class="legal">
<p class="eyebrow">TÚ ELIGES</p>
<h1>Política de cookies.</h1>
<p>Esta política describe los mecanismos de almacenamiento del navegador de {{ config('catalogue.portal_name') }}, operado por {{ config('catalogue.institution') }}. Forma parte de la información de privacidad del portal y se interpreta junto con su aviso institucional y la legislación mexicana aplicable en materia de protección de datos personales.</p>
<h2>Qué son y para qué se utilizan</h2>
<p>Las cookies son pequeños archivos que el navegador conserva para reconocer una sesión o recordar una preferencia. No son programas. Algunas permiten iniciar sesión y proteger formularios; otras, cuando se implementan, pueden servir para medición o personalización.</p>
<h2>Cookies necesarias</h2>
<div class="table-scroll">
<table>
<thead>
<tr><th>Mecanismo</th><th>Finalidad</th><th>Duración</th></tr></thead>
<tbody>
<tr><td>Cookie de sesión</td><td>Mantener el acceso y proteger operaciones de tu cuenta.</td><td>{{ config('session.lifetime') }} minutos de inactividad; según configuración de sesión.</td></tr>
<tr><td>Protección de formularios</td><td>Impedir envíos ajenos a tu sesión.</td><td>Asociada a la sesión.</td></tr>
<tr><td>cookie_preferences</td><td>Recordar tu decisión sobre cookies opcionales.</td><td>180 días.</td></tr>
<tr><td>Recordarme, si lo seleccionas</td><td>Conservar el acceso entre visitas en ese dispositivo.</td><td>Persistente hasta su vencimiento o revocación; puedes cerrar sesión y borrar las cookies.</td></tr></tbody></table></div>
<p>Las cookies necesarias no se desactivan con «Rechazar opcionales» porque mantienen funciones solicitadas y medidas de seguridad. Puedes bloquearlas en tu navegador, aunque el acceso a cuentas y algunos formularios dejarán de funcionar.</p>
<h2>Cookies opcionales</h2>
<p>La versión base de este repositorio no incluye publicidad ni servicios de analítica. Aceptar cookies opcionales no activa por sí mismo un proveedor de seguimiento. Si la institución incorpora uno, deberá identificarlo, informar sus finalidades y duración y condicionar su carga a la preferencia correspondiente. Rechazar no limita la consulta del catálogo ni las exportaciones habilitadas.</p>
<h2>Autenticación y comprobación de seguridad</h2>
<p>Google se abre cuando eliges autenticarte con ese proveedor. Sus cookies se rigen por su propia política. Cloudflare Turnstile puede ejecutarse en operaciones protegidas y procesar señales técnicas para distinguir uso legítimo y abuso; no se usa aquí como herramienta publicitaria. Los destinos externos de los artículos tienen sus propias políticas.</p>
<h2>Cambiar o retirar tu elección</h2>
<p>Puedes abrir «Preferencias de cookies» en el pie de cualquier página y guardar otra elección. También puedes eliminar cookies desde tu navegador. La nueva preferencia se aplicará a operaciones futuras; retirar una elección no revierte tratamientos ya efectuados de forma lícita.</p><button class="button secondary" data-cookie-open type="button">Cambiar preferencias</button>
<h2>Contacto y actualización</h2>
<p>Para dudas o derechos relacionados con tus datos, consulta el <a href="{{ route('privacy') }}">aviso de privacidad</a>. La institución operadora debe revisar esta política cuando cambien la configuración del sistema, sus proveedores o los mecanismos de almacenamiento utilizados.</p></article>
@endsection
