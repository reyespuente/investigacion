# Repositorio académico

Sistema abierto en **Laravel 13**, Blade, JavaScript y MariaDB para consultar publicaciones, descubrir investigadores y mantener sus perfiles académicos. Cada institución configura su identidad y aporta sus propios datos. **Este repositorio no contiene padrones, correos institucionales, publicaciones, documentos ni claves de ninguna instalación.**

Desarrollado por Reyes Guadalupe Puente Balcázar · [contacto@reyespuente.com](mailto:contacto@reyespuente.com). Código bajo licencia MIT; los documentos y metadatos importados conservan sus propias condiciones y derechos.

## Qué permite

- Portada con publicaciones recientes, tarjetas y ranking por autorías confirmadas. Las cifras proceden del catálogo y no se presentan como medidas de calidad o impacto.
- Búsqueda mientras se escribe, filtros por año, tipo, resumen y perfil, orden y paginación.
- Fichas con autores ordenados, afiliaciones y ORCID disponibles, resumen y versiones por idioma, revista/congreso, DOI, bibliografía, fuentes y enlace al original.
- Exportación filtrada en CSV, XLSX, JSON, XML, BibTeX y RIS; ficha bibliográfica PDF. CSV/XLSX protegen valores que podrían interpretarse como fórmulas.
- Copia de citas con CSL: APA 7, Chicago autor-fecha, Vancouver, IEEE, BibTeX y RIS. Es necesario revisar las exigencias particulares de cada revista.
- Registro con Google limitado a los dominios configurados. Reclamar un perfil exige correo verificado, coincidencia exacta con el padrón privado y un perfil disponible.
- Biografía, foto, departamento y contacto público voluntario. El correo de acceso permanece privado.
- Aportaciones directas del investigador, edición de publicaciones compartidas con autorización, control de versiones e historial.
- PDFs aportados por sus titulares, certificaciones y documentos privados o públicos. Almacenamiento local o S3 compatible y visor PDF.js. El sistema no descarga artículos automáticamente.
- Administración de cuentas de cualquier dominio, cambios y recuperación de contraseña, revisión de publicaciones y atribuciones, desactivación de accesos y auditoría.
- **Asistente de instalación protegido** y administración de identidad, dominio, correos permitidos, SMTP/sendmail, almacenamiento local/S3, Google y Turnstile. Los cambios visuales y de servicios no necesitan recompilar.
- Turnstile validado en el servidor, límites de solicitudes, CSRF, comprobación de derechos de archivos y preferencias de cookies.

## Requisitos

- Linux; PHP **8.3 o superior** y Composer 2. El archivo de bloqueo se resuelve para PHP 8.3.
- Extensiones PHP: PDO MySQL, mbstring, XML/DOM, cURL, fileinfo, GD, ZIP, intl y las requeridas por Laravel.
- MariaDB **10.6 o superior**; el desarrollo se verifica con MariaDB 11.8. También se usa SQLite en pruebas aisladas.
- Node **22.12 o superior** y npm para compilar los recursos. No es necesario ejecutar Node permanentemente en producción.
- HTTPS en producción. Acceso a Google, Cloudflare y los proveedores de almacenamiento y correo que decida la institución.

## Instalación local

```bash
git clone https://github.com/reyespuente/investigacion.git
cd investigacion
composer install
npm ci
cp .env.example .env
chmod 600 .env
php artisan key:generate
```

Crea una base vacía y una cuenta de MariaDB limitada a esa base. Configura `DB_*` y una contraseña inicial fuerte en `ADMIN_PASSWORD` (al menos 16 caracteres). El administrador inicial utiliza `ADMIN_NAME` y `ADMIN_EMAIL`; nunca hay una contraseña predeterminada en el código.

```bash
php artisan repository:install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Abre `http://localhost:8000`. El primer acceso del administrador exige cambiar la contraseña temporal. Repetir el seeder **no restablece contraseñas, cuentas, reclamaciones ni datos ya editados**. Una instalación sin datos muestra estados vacíos.

En local puedes usar `TURNSTILE_ENABLED=false`. En producción el servidor impide omitir Turnstile: debes configurar sus claves y el nombre de host. La configuración inicial de correo usa `MAIL_MAILER=log`; los enlaces de recuperación quedan en el registro local y no se envían a destinatarios reales.

## Configuración por institución

Consulta todas las variables en [`.env.example`](.env.example).

| Variable | Uso |
|---|---|
| `APP_URL` | URL pública, sin barra final; debe coincidir con OAuth y HTTPS. |
| `INSTITUTION_NAME`, `INSTITUTION_SHORT_NAME`, `PORTAL_NAME`, `INSTITUTION_NETWORK` | Identidad inicial. |
| `BRAND_PRIMARY`, `BRAND_ACCENT` | Colores hexadecimales iniciales. El panel valida contraste sobre blanco. |
| `INSTITUTION_LOGO`, `NETWORK_LOGO` | Rutas iniciales relativas a `public/`, por ejemplo `brand/institution.webp`. Los archivos quedan fuera de Git. |
| `ALLOWED_EMAIL_DOMAINS` | Uno o varios dominios exactos, separados por comas: `universidad.edu.mx,personal.universidad.edu.mx`. |
| `INSTITUTION_PRIVACY_URL`, `PRIVACY_CONTACT` | Aviso institucional y contacto competente. |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | Solo creación del administrador inicial. No determinan el rol de nuevos registros. |
| `GOOGLE_*` | Identificador, secreto y callback OAuth. |
| `TURNSTILE_*` | Protección de operaciones; el host y la acción se comprueban en servidor. |
| `FILESYSTEM_DISK`, `AWS_*` | Almacenamiento local o S3 compatible. |
| `MAIL_*` | SMTP o registro local. |

El `.env` proporciona los valores iniciales. Después, los cambios guardados desde Administración → Identidad y logotipos o Servicios y acceso tienen prioridad y se aplican sin editar archivos. Las claves de servicios se almacenan cifradas en la base y los campos vacíos conservan el valor anterior. La clave de cifrado, conexión inicial a la base, entorno de ejecución y permisos permanecen bajo control del servidor. Al cambiar manualmente el `.env`, ejecuta `php artisan config:cache`. Los dominios no admiten coincidencias por sufijo: permitir `universidad.edu.mx` no permite `universidad.edu.mx.otro-sitio.com` ni subdominios no declarados.

## Asistente y servicios

Ejecuta `php artisan repository:install` en el servidor. Prepara las migraciones y el primer administrador sin abrir un instalador público y no restablece administradores existentes. El primer administrador cambia su contraseña al entrar y continúa en `/admin/instalacion`. Desde allí accede a identidad, servicios y revisión del catálogo.

Modificar servicios exige volver a introducir la contraseña actual. Las contraseñas y claves no se incluyen en HTML ni en el historial de cambios. El instalador no configura DNS, certificados, el cliente de Google Cloud ni la instalación del MTA; muestra lo pendiente y permite comprobar las conexiones configuradas.

## Google OAuth, paso a paso

1. Crea o elige un proyecto en [Google Cloud Console](https://console.cloud.google.com/).
2. En **Google Auth Platform**, configura Branding, Audience y Data Access. Añade el nombre del portal, contacto, dominio verificado y la URL de privacidad. Usa solo `openid`, `email` y `profile`.
3. Si el proyecto pertenece a una organización Google Workspace y todos los usuarios elegibles son de esa organización, puedes elegir audiencia interna. En otros casos usa externa; mientras esté en pruebas, añade los usuarios de prueba. La restricción real del dominio también se verifica en Laravel.
4. En **Clients**, crea un cliente de tipo **Web application**. Registra los callbacks exactos:

```text
http://localhost:8000/auth/google/callback
https://TU-DOMINIO/auth/google/callback
```

5. Configura las credenciales desde Administración → Servicios y acceso, o usa inicialmente `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI`. El panel calcula el callback a partir de la URL pública. No publiques el secreto ni el archivo JSON descargado de Google. Reinicia/cacha la configuración.
6. Prueba el acceso con una cuenta institucional y otra ajena. La segunda debe rechazarse. Para reclamar un perfil, importa primero el archivo privado de correos.

El parámetro de dominio enviado a Google es una ayuda visual; **la aplicación verifica el correo devuelto, su estado verificado y el dominio exacto**. El identificador estable de Google evita unir cuentas diferentes. No se almacenan contraseñas de Google ni sus tokens de acceso.

Referencia: [OAuth para aplicaciones web](https://developers.google.com/identity/protocols/oauth2/web-server) y [OpenID Connect de Google](https://developers.google.com/identity/openid-connect/openid-connect).

## Importar el catálogo de una institución

El seeder prepara únicamente el administrador. Los datos se cargan aparte; nunca guardes las importaciones en Git. Los ejemplos de estructura están en [docs/IMPORTACION.md](docs/IMPORTACION.md).

```bash
install -d -m 700 storage/app/private/imports
# Copia por un canal privado los archivos de tu institución a esa carpeta.
php artisan catalog:researchers storage/app/private/imports/researchers.json
php artisan catalog:import storage/app/private/imports/catalogue.jsonl.gz
php artisan catalog:claims storage/app/private/imports/claims.csv
```

Primero perfiles, luego publicaciones y finalmente correos de reclamación. Los identificadores de origen enlazan las referencias con los perfiles. `catalog:import` admite JSONL y JSONL.GZ, conserva la cola de revisión y no sobrescribe publicaciones editadas por usuarios. Los comandos son repetibles por identificador. Una misma publicación puede corresponder a varios investigadores sin duplicarse.

Los correos de reclamación se transforman en HMAC con `APP_KEY`; el padrón de la aplicación no almacena esos correos en texto. No cambies `APP_KEY` rutinariamente: su rotación afecta sesiones, cifrado y reclamaciones, y exige reimportar el archivo privado de correos. Los datos de cuentas y el contacto voluntariamente público se almacenan por separado.

## Qué se copia manualmente al servidor y NO está en GitHub

1. **`.env` de producción**: `APP_KEY`, conexión de base y valores iniciales de servicios/seguridad. Las claves guardadas desde administración están cifradas en la base, por lo que también necesitas su respaldo y la misma `APP_KEY` al trasladar una instalación existente. Nunca copies una configuración local sin adaptarla.
2. **Archivos privados de importación**: perfiles, catálogo completo y correos de reclamación. Guarda también su manifiesto y las decisiones de revisión. No se requieren RFC.
3. **Logotipos propios** si se utilizan rutas iniciales `public/brand/`. Los logotipos subidos desde administración están en el almacenamiento y sus rutas en la base.
4. **Base de la aplicación** si migras una instalación en uso: incluye cuentas, reclamaciones, identidad visual, documentos y ediciones. En ese caso restaura su respaldo; no sustituyas sus tablas por el catálogo de recopilación.
5. **Objetos del almacenamiento** si cambia el bucket o se usa disco local. Conserva sus rutas relativas. Si se conserva el mismo bucket, basta configurar el acceso correcto.
6. Opcionalmente, el archivo histórico de respuestas y evidencias de recopilación, separado del sistema. Un volcado de ese archivo histórico no sustituye la base de Laravel.

`vendor/`, `node_modules/` y `public/build/` tampoco se incluyen: se reproducen con `composer install`, `npm ci` y `npm run build`. Instrucciones completas en [docs/DESPLIEGUE.md](docs/DESPLIEGUE.md).

## Almacenamiento y documentos

Para S3 compatible usa `FILESYSTEM_DISK=s3`, endpoint HTTPS, región, bucket, claves y `AWS_USE_PATH_STYLE_ENDPOINT=true` cuando lo requiera el proveedor. Mantén el bucket **privado**. Las credenciales permanecen en servidor; el navegador recibe archivos mediante rutas que comprueban permisos. El visor usa el mismo origen y no requiere hacer público el bucket ni desactivar CORS globalmente.

```bash
php artisan catalog:storage-check
```

También puedes ejecutar la prueba desde Servicios y acceso. La prueba crea un objeto temporal con UUID, comprueba su lectura y retira ese mismo objeto. No enumera ni modifica archivos ajenos. En producción limita la clave al bucket/prefijo de esta instalación. El acceso del proveedor de correo es independiente de la clave S3.

Cada archivo recuerda su disco de origen. Las conexiones S3 guardadas se conservan por versión: cambiar de bucket afecta nuevas subidas y no reescribe los documentos anteriores. No retires sus credenciales u objetos hasta completar una migración real. Respaldar solo el `.env` no basta si hay conexiones guardadas en el panel.

Los documentos admitidos son PDF de hasta 20 MB. Se requiere declaración de derechos y una elección explícita de publicación; el estado inicial es privado. Los archivos de licencia privada no se publican aunque se solicite. Las imágenes se convierten a WebP para quitar metadatos y evitar SVG/HTML ejecutable.

## Turnstile y SMTP

Registra el dominio en [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/get-started/), configura la clave pública y el secreto, y activa `TURNSTILE_ENABLED=true`. El servidor exige validación con hostname y acción coincidentes. Los tokens son de un solo uso y expiran; hay que generar uno nuevo para cada exportación u operación protegida. [Validación oficial](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).

Configura el correo desde Administración → Servicios y acceso; la prueba de envío se dirige solo al correo del administrador que la solicita. También puedes definir los valores iniciales en `.env`. Para SMTP configura `MAIL_MAILER=smtp`, host, puerto, usuario, contraseña y remitente autorizado. `MAIL_SCHEME=smtp` usa el transporte con negociación TLS del servidor; para SMTPS normalmente se usa `smtps` y puerto 465. Usa los parámetros de tu proveedor, y configura SPF/DKIM/DMARC del dominio remitente. Las notificaciones de recuperación están en HTML y no revelan si una dirección tiene cuenta. La alternativa `sendmail` usa el MTA del servidor, no un binario PHP ni un comando arbitrario editable desde la web. El operador instala el servicio y configura `MAIL_SENDMAIL_PATH` si su ubicación difiere. `log` no entrega mensajes y sirve solo para desarrollo.

## Privacidad, derechos y cookies

El sistema incluye páginas de privacidad y cookies y enlace al aviso de cada institución. **Son textos base, no una certificación legal**. Antes de publicar, la institución debe verificar responsable, finalidades, contacto de derechos, conservación, proveedores y transferencias según su régimen jurídico. En México distingue la legislación de sujetos obligados de la aplicable a particulares. No incluyas datos personales en el repositorio fuente.

No hay analítica ni publicidad en la base. Las cookies opcionales no activan servicios por sí mismas; cualquier integración futura debe respetar la preferencia. Las cookies necesarias mantienen sesión, CSRF y preferencia. La elección puede modificarse desde el pie. El sistema conserva historial; ocultar un archivo no implica eliminar las evidencias o respaldos sujetos a conservación.

Las subidas del investigador requieren derechos propios o autorización/licencia válida. Compartir una referencia o reclamar un perfil no otorga automáticamente derechos sobre el PDF editorial.

## Pruebas y mantenimiento

```bash
php artisan test
vendor/bin/pint --test
npm ci
npm run build
node scripts/test-citations.mjs
python3 scripts/check-secrets.py
composer audit
npm audit --omit=dev
```

Las pruebas usan SQLite en memoria y archivos simulados: no borran la base real ni escriben en el bucket de producción. Cubren dominios y correo verificado, reclamaciones, autorización por publicación, versiones, privacidad de archivos, exportaciones, Turnstile, importaciones repetidas, identidad visual y páginas principales. La configuración cifrada, el instalador y la conservación del backend de archivos también tienen pruebas aisladas. Ver [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md) para estructura y decisiones.

## Dependencias y atribución

Laravel y Socialite; Flysystem y AWS SDK para S3; Dompdf para fichas; OpenSpout para XLSX; PDF.js; Citation.js y CSL para citas; Bootstrap Icons e Instrument Sans. Tipografía, iconos y estilos se sirven localmente. Las plantillas CSL proceden de [citation-style-language/styles](https://github.com/citation-style-language/styles) y la localización de [citation-style-language/locales](https://github.com/citation-style-language/locales); conserva sus avisos de atribución y licencia. La licencia MIT del código no modifica las licencias de dependencias, estilos, marcas o documentos institucionales.
