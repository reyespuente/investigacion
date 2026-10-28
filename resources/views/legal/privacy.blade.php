
@extends('layouts.app')
@section('title','Privacidad')
@section('content')<article class="legal">
<p class="eyebrow">TRANSPARENCIA Y CONTROL DE TUS DATOS</p>
<h1>Privacidad del repositorio.</h1>
<p>{{ config('catalogue.institution') }} identifica este portal como su repositorio académico. Esta página explica el funcionamiento del sistema y complementa el aviso de privacidad de la institución; no sustituye sus disposiciones ni sus canales de atención.</p>
@if(config('catalogue.privacy_url'))<p><a class="button secondary" href="{{ config('catalogue.privacy_url') }}" target="_blank" rel="noopener noreferrer">Consultar aviso institucional <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></p>
@endif<h2>Datos que utiliza el portal</h2>
<p>El catálogo reúne referencias bibliográficas, nombres de autores, identificadores académicos, afiliaciones declaradas, resúmenes y enlaces a las fuentes. Las referencias pendientes de revisión permanecen fuera de la consulta pública. Para las cuentas se conservan nombre, correo, identificador de Google cuando se utiliza ese acceso, credenciales protegidas cuando corresponda y registros de seguridad.</p>
<p>El correo del padrón usado para reclamar perfiles no se muestra públicamente: su coincidencia se comprueba mediante un código de autenticación calculado en el servidor. El sistema no necesita RFC, CURP, domicilio personal ni documentos de identidad para reclamar un perfil. No incluyas esos datos en tu biografía ni en archivos públicos.</p>
<h2>Finalidades</h2>
<p>Los datos permiten difundir la producción académica, identificar autores, gestionar cuentas y reclamaciones, mantener referencias y documentos, generar citas y exportaciones, atender correcciones y proteger el servicio. Las ediciones y decisiones administrativas conservan un historial para asegurar trazabilidad.</p>
<h2>Información pública y decisiones del investigador</h2>
<p>Los metadatos académicos del catálogo y el nombre del perfil son públicos. La biografía, fotografía, contacto profesional y documentos que añadas serán públicos únicamente en los campos y opciones de publicación correspondientes. El correo de acceso no se convierte automáticamente en correo público. Puedes dejar vacíos los campos opcionales y retirar de la consulta pública los documentos que hayas compartido.</p>
<p>Solo debes subir archivos que tengas derecho a almacenar y distribuir. Las licencias y autorizaciones del documento original siguen vigentes. La titularidad de una cuenta no transfiere derechos de autor al repositorio.</p>
<h2>Servicios externos</h2>
<p>Si eliges Google, te redirigimos a ese proveedor para autenticarte y recibimos los datos básicos de identidad autorizados. El portal no recibe tu contraseña de Google. Cloudflare Turnstile comprueba determinadas operaciones para reducir abuso y puede procesar información técnica del navegador y la conexión. El proveedor de almacenamiento y el servicio de correo intervienen en el resguardo de archivos y el envío de comunicaciones necesarias. Sus ubicaciones, contratos y condiciones deben corresponder a la configuración y obligaciones de la institución operadora.</p>
<p>Los enlaces a revistas, DOI, ORCID y otras fuentes abren servicios externos con sus propios avisos. El repositorio no controla sus cookies ni su disponibilidad.</p>
<h2>Conservación y protección</h2>
<p>Se usan permisos de acceso por función, contraseñas con hash, comprobación de identidad, registro de modificaciones y almacenamiento privado de documentos con acceso controlado. La institución determina los plazos de conservación de cuentas, evidencias y archivos conforme a sus obligaciones archivísticas, académicas y de protección de datos. Solicitar que un documento deje de mostrarse no elimina por sí mismo las copias de respaldo ni los registros sujetos a conservación legal.</p>
<h2>Acceso, rectificación, cancelación y oposición</h2>
<p>Puedes editar los datos públicos de tu perfil desde tu cuenta. Para ejercer derechos sobre datos que no puedas modificar, solicitar baja de cuenta, señalar atribuciones incorrectas o pedir revisión de un documento, utiliza los canales del aviso institucional.</p>
@if(config('catalogue.privacy_contact'))<p>Contacto de privacidad: <a href="mailto:{{ config('catalogue.privacy_contact') }}">{{ config('catalogue.privacy_contact') }}</a>.</p>
@endif<p>No envíes documentos personales ni claves por formularios de publicaciones o comentarios bibliográficos. La unidad competente podrá indicarte, por un canal adecuado, los requisitos para acreditar la titularidad de una solicitud.</p>
<h2>Cookies y cambios</h2>
<p>Consulta la <a href="{{ route('cookies') }}">política de cookies</a> y modifica tu elección desde el pie del portal. La institución debe mantener actualizados este texto, su aviso y sus datos de contacto cuando cambien las finalidades, proveedores o reglas aplicables.</p></article>
@endsection
