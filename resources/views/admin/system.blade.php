@extends('layouts.app')
@section('title','Configuración del sistema')
@section('content')
<a class="back-link" href="{{ route('admin.setup') }}">Asistente de instalación</a><div class="page-heading"><p class="eyebrow">ADMINISTRACIÓN</p><h1>Tu institución, tus servicios.</h1><p class="muted">Configura las conexiones del portal. Deja vacíos los campos de claves si deseas conservar las que ya están guardadas.</p></div>
<form class="form-panel wide-form" method="post" action="{{ route('admin.system.update') }}">
@csrf
<section class="system-section"><h2>Dominio y acceso</h2><p class="small muted">Cambiar esta URL no configura DNS ni certificados. Prepara el dominio y HTTPS en el servidor y actualiza el callback en Google antes de utilizarlo.</p>
@include('partials.field',['name'=>'app_url','label'=>'URL pública del portal','type'=>'url','value'=>$settings['app_url'],'required'=>true,'help'=>'URL raíz sin ruta, por ejemplo https://investigacion.institucion.edu.mx'])
@include('partials.field',['name'=>'domains','label'=>'Dominios de correo permitidos','value'=>$settings['domains'],'required'=>true,'help'=>'Separados por comas, sin @ ni comodines. Las cuentas creadas por el administrador pueden pertenecer a otros dominios.'])
@include('partials.field',['name'=>'privacy_url','label'=>'URL del aviso de privacidad institucional','type'=>'url','value'=>$settings['privacy_url']])
@include('partials.field',['name'=>'privacy_contact','label'=>'Correo para atención de privacidad','type'=>'email','value'=>$settings['privacy_contact']])
</section>
<section class="system-section"><h2>Correo saliente</h2><p class="small muted">SMTP utiliza un proveedor o servidor de correo. El modo local usa el servicio sendmail instalado por el operador del servidor. El registro local conserva mensajes para desarrollo y no los entrega.</p>
@include('partials.field',['name'=>'mail_mode','label'=>'Forma de envío','type'=>'select','value'=>$settings['mail_mode'],'options'=>['smtp'=>'SMTP','sendmail'=>'Servicio local del servidor (sendmail)','log'=>'Solo registro local · desarrollo']])
<div class="form-grid">
@include('partials.field',['name'=>'mail_host','label'=>'Servidor SMTP','value'=>$settings['mail_host']])
@include('partials.field',['name'=>'mail_port','label'=>'Puerto','type'=>'number','value'=>$settings['mail_port']])
@include('partials.field',['name'=>'mail_scheme','label'=>'Transporte','type'=>'select','value'=>$settings['mail_scheme'],'options'=>['smtp'=>'SMTP con negociación TLS','smtps'=>'SMTPS · TLS desde el inicio']])
@include('partials.field',['name'=>'mail_username','label'=>'Usuario SMTP','type'=>'text','autocomplete'=>'off','help'=>$settings['has_mail_username']?'Ya hay un usuario configurado; déjalo vacío para conservarlo.':'Introduce el usuario del proveedor, si lo requiere.'])
</div>
@include('partials.field',['name'=>'mail_password','label'=>'Contraseña SMTP','type'=>'password','autocomplete'=>'new-password','help'=>$settings['has_mail_password']?'Hay una contraseña guardada. Este campo no muestra su valor.':'Se guardará cifrada en el servidor.'])
<div class="form-grid">
@include('partials.field',['name'=>'mail_from_address','label'=>'Correo remitente','type'=>'email','value'=>$settings['mail_from_address'],'required'=>true])
@include('partials.field',['name'=>'mail_from_name','label'=>'Nombre del remitente','value'=>$settings['mail_from_name'],'required'=>true])
</div></section>
<section class="system-section"><h2>Almacenamiento de archivos</h2><p class="small muted">Los archivos anteriores conservan su conexión. Cambiar el backend no los mueve ni los elimina. Mantén disponibles sus credenciales y objetos hasta completar una migración de archivos.</p>
@include('partials.field',['name'=>'storage_mode','label'=>'Destino de nuevas subidas','type'=>'select','value'=>$settings['storage_mode'],'options'=>['local'=>'Almacenamiento privado del servidor','s3'=>'Bucket S3 compatible']])
<div class="form-grid">
@include('partials.field',['name'=>'s3_endpoint','label'=>'Endpoint S3 (opcional para AWS)','type'=>'url','value'=>$settings['s3_endpoint']])
@include('partials.field',['name'=>'s3_region','label'=>'Región','value'=>$settings['s3_region']])
@include('partials.field',['name'=>'s3_bucket','label'=>'Bucket','value'=>$settings['s3_bucket']])
@include('partials.field',['name'=>'s3_key','label'=>'ID de acceso','type'=>'password','autocomplete'=>'new-password','help'=>$settings['has_s3_credentials']?'Credenciales configuradas; vacío conserva las actuales.':'Proporciona una clave limitada al bucket de esta instalación.'])
</div>
@include('partials.field',['name'=>'s3_secret','label'=>'Clave privada S3','type'=>'password','autocomplete'=>'new-password'])
<label class="checkbox"><input type="checkbox" name="s3_use_path_style_endpoint" value="1" @checked($settings['s3_use_path_style_endpoint'])> Usar endpoint con ruta de bucket (path style)</label>
</section>
<section class="system-section"><h2>Google y registro institucional</h2><p class="small muted">Crea un cliente OAuth web en Google Cloud y registra la URL pública del portal seguida de <code>/auth/google/callback</code>. El dominio permitido se verifica también en el servidor.</p>
@include('partials.field',['name'=>'google_client_id','label'=>'Google Client ID','value'=>$settings['google_client_id']])
@include('partials.field',['name'=>'google_client_secret','label'=>'Google Client Secret','type'=>'password','autocomplete'=>'new-password','help'=>$settings['has_google_client_secret']?'Ya hay un secreto guardado. Déjalo vacío para conservarlo.':'Introduce el secreto del cliente web.'])
<p class="small muted">Callback actual: <code>{{ $settings['google_redirect'] }}</code></p>
</section>
<section class="system-section"><h2>Cloudflare Turnstile</h2><label class="checkbox"><input type="checkbox" name="turnstile_enabled" value="1" @checked($settings['turnstile_enabled'])> Activar protección de operaciones</label><p class="small muted">En producción debe permanecer activa. Configura el dominio del portal en Cloudflare.</p>
@include('partials.field',['name'=>'turnstile_site_key','label'=>'Clave pública (site key)','value'=>$settings['turnstile_site_key']])
@include('partials.field',['name'=>'turnstile_secret_key','label'=>'Clave privada (secret key)','type'=>'password','autocomplete'=>'new-password','help'=>$settings['has_turnstile_secret_key']?'Hay una clave privada configurada.':'La clave privada nunca se envía al navegador.'])
@include('partials.field',['name'=>'turnstile_hostname','label'=>'Hostname permitido','value'=>$settings['turnstile_hostname'],'help'=>'Sin protocolo ni ruta, por ejemplo investigacion.institucion.edu.mx.'])
</section>
<section class="system-section"><h2>Confirmar cambios</h2>
@include('partials.field',['name'=>'current_password','label'=>'Tu contraseña actual de administrador','type'=>'password','required'=>true,'autocomplete'=>'current-password'])
<button class="button" type="submit">Guardar configuración</button></section>
</form>
<section class="article-section"><h2>Comprobar conexiones guardadas</h2><p class="muted small">Guarda primero los cambios. La prueba de correo se dirige únicamente a tu propia cuenta de administrador. La de almacenamiento crea y retira un objeto temporal exclusivo.</p><div class="form-grid">
<form method="post" action="{{ route('admin.system.mail') }}" class="form-panel">
@csrf
@include('partials.field',['name'=>'current_password','label'=>'Contraseña actual','type'=>'password','required'=>true,'autocomplete'=>'current-password'])
<button class="button secondary">Enviar correo de prueba a mi cuenta</button></form>
<form method="post" action="{{ route('admin.system.storage') }}" class="form-panel">
@csrf
@include('partials.field',['name'=>'current_password','label'=>'Contraseña actual','type'=>'password','required'=>true,'autocomplete'=>'current-password'])
<button class="button secondary">Probar almacenamiento activo</button></form>
</div></section>
@endsection
