# Estructura y límites

Laravel sirve Blade y endpoints de búsqueda en el mismo origen. Vite compila CSS y JavaScript; no hay un segundo servidor de aplicación ni un frontend separado. Las tipografías, iconos, estilos de cita y PDF.js se sirven localmente.

## Tablas

- `users`: cuentas, identificador estable Google, rol, activación y cambio obligatorio de contraseña.
- `researchers`: perfil académico, identidad de origen, propietario opcional, HMAC de correo de reclamación y contacto público voluntario.
- `publications`: bibliografía normalizada, autores ordenados, metadatos/evidencias del origen, estado, procedencia de edición y número de revisión.
- `publication_researcher`: relación de cada autor con un trabajo, estado de confirmación y tipo de evidencia.
- `documents`: archivo, investigador, publicación opcional, licencia declarada, visibilidad y responsable de la subida. Los objetos son privados en el almacenamiento.
- `audit_events`: versiones y motivos de reclamaciones, ediciones y decisiones administrativas.
- `site_settings`: identidad y ajustes del panel. Los secretos de correo/Google/Turnstile se guardan cifrados; las claves no se incluyen en respuestas ni en auditoría. El entorno conserva conexión de base, cifrado y valores iniciales.
- `storage_profiles`: conexiones S3 cifradas e inmutables por versión. Cada documento, avatar o logotipo guarda su disco; cambiar el backend activo no reubica objetos anteriores.
- Tablas normales de Laravel: sesiones, caché, recuperación de contraseña y soporte de trabajos.

## Autorización

Google verifica la cuenta; el servidor comprueba dominio y correo verificado. Reclamar exige coincidencia exacta con el HMAC del padrón, un perfil libre y una cuenta sin otro perfil. Hay restricciones únicas y bloqueo transaccional de fila para evitar reclamaciones simultáneas.

Un investigador edita publicaciones que aportó o que tienen relación confirmada con su perfil. No obtiene acceso por coincidir en el texto de autores. Los cambios compartidos conservan un historial y utilizan versión para detectar ediciones concurrentes. Administradores pueden revisar también atribuciones; publicar un trabajo no confirma automáticamente sus relaciones pendientes.

La condición de publicación pública es `status=published`. La revisión y las referencias incompletas permanecen privadas. Las aportaciones del investigador se publican directamente y se identifican como tales; no se presentan como validación editorial externa.

Las rutas de archivo comprueban visibilidad y propietario; conocer un ID no permite descargar un documento privado. Un PDF público relacionado con una publicación que deja de ser pública también queda protegido. Una retirada de visibilidad no elimina el objeto ni el historial.

## Integraciones pendientes de configurar por el operador

La aplicación incluye las integraciones, pero no puede completar por sí sola el alta del cliente OAuth, la verificación del dominio de Google, las claves de Turnstile, credenciales SMTP y las decisiones jurídicas de la institución. Las pruebas automatizadas simulan Google/Cloudflare y correo para no generar accesos o envíos externos. La entrega real de SMTP, el OAuth del dominio y Turnstile en producción deben verificarse con las claves definitivas.

## Recopilación futura

El sistema base no trae un bot ni realiza scraping. Un recolector futuro debe conservar identificadores/procedencia, evitar PDFs sin autorización y enviar candidatos al estado de revisión. No debe modificar publicaciones ya editadas ni dar por confirmada una identidad por nombre parecido.

El catálogo histórico completo, que puede contener datos internos de recopilación, se mantiene separado. La aplicación conserva las fichas y la procedencia importadas sin publicar sus archivos privados de origen.

## Instalación y configuración

`repository:install` funciona por CLI y no crea un punto público para tomar control de una instalación vacía. Crea el primer administrador con contraseña fuerte o conserva el existente. El asistente web está detrás de autenticación, rol administrativo y cambio inicial de contraseña. Los cambios de servicios exigen confirmar la contraseña actual.

Los valores iniciales vienen del entorno; los guardados en el panel tienen prioridad. La configuración se aplica a peticiones web, comandos relevantes de catálogo y trabajos de cola. Las credenciales permanecen cifradas en las cachés de configuración y en la base; solo se descifran en el proceso que necesita la conexión. No se permite indicar comandos ejecutables o rutas arbitrarias del servidor desde el panel.

Los perfiles de almacenamiento se retienen para mantener acceso a los objetos de cada conexión. Esto no mueve archivos entre proveedores. Una migración de objetos requiere copiar los objetos y actualizar referencias de forma controlada, conservando una copia verificable; no basta cambiar el bucket activo.

La `APP_KEY` es imprescindible para descifrar servicios y perfiles además de las sesiones y HMAC de reclamación. No debe regenerarse al restaurar una base existente. Conserva su respaldo por separado y restringe acceso tanto a la base como a los objetos.
