# Despliegue en Linux

Ejecuta los comandos con un usuario de despliegue, no como root salvo las tareas administrativas del sistema. Ajusta los ejemplos a tu ruta, versión de PHP y usuario del servicio. No ejecutes `migrate:fresh` en una instalación con datos.

## Código y configuración

```bash
git clone https://github.com/reyespuente/investigacion.git /srv/repositorio
cd /srv/repositorio
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.example .env
```

Configura `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://TU-DOMINIO`, `SESSION_SECURE_COOKIE=true`, MariaDB, identidad, dominio(s), claves y remitente. Crea una contraseña inicial aleatoria de al menos 16 caracteres. Genera `APP_KEY` **una sola vez por instalación** antes de importar correos.

```bash
php artisan key:generate
php artisan repository:install --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si migras una instalación existente, restaura primero la base de Laravel y conserva su `APP_KEY`. No regeneres la clave ni ejecutes de nuevo un alta inicial para reemplazar cuentas. Después aplica las migraciones pendientes y recompila cachés.

El único directorio que debe publicar el servidor web es **`/srv/repositorio/public`**. No expongas la raíz del proyecto, `.env`, `storage/app/private`, importaciones ni respaldos. El servidor integrado de Artisan es solo para desarrollo.

## Permisos

Ejemplo con usuario `deploy` y grupo PHP-FPM `www-data`:

```bash
sudo chown -R deploy:www-data /srv/repositorio
find /srv/repositorio -type d -not -path '*/.git/*' -exec chmod 755 {} +
find /srv/repositorio -type f -not -path '*/.git/*' -exec chmod 644 {} +
sudo chmod 750 /srv/repositorio/.git
sudo chmod 640 /srv/repositorio/.env
sudo chmod 755 /srv/repositorio/artisan
find /srv/repositorio/storage /srv/repositorio/bootstrap/cache -type d -exec chmod 2770 {} +
find /srv/repositorio/storage /srv/repositorio/bootstrap/cache -type f -exec chmod 660 {} +
```

`640` permite que PHP-FPM lea el `.env` mediante su grupo sin hacerlo público. `600` también es válido si el propietario es el usuario que lo debe leer. Los archivos fuente no necesitan escritura del servidor web. No uses `777`. Si usas SELinux/AppArmor, configura también su política de acceso; no la desactives para resolver permisos.

Los directorios de escritura son `storage` y `bootstrap/cache`. Mantén las importaciones fuera de la raíz pública y limita su lectura al operador. Tras compilar/instalar con el usuario de despliegue, verifica nuevamente propietarios y permisos.

## Nginx y PHP-FPM

Configura un host HTTPS, certificado válido y este bloque de aplicación dentro del servidor correspondiente:

```nginx
root /srv/repositorio/public;
index index.php;
client_max_body_size 24m;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
}
location ~ /\.(?!well-known).* {
    deny all;
}
```

Ajusta el socket a PHP instalado. Configura también `upload_max_filesize=20M`, `post_max_size=24M` y un límite de memoria adecuado (recomendado 256 MB) en el PHP-FPM del sitio. Reinicia o recarga los servicios al cambiar su configuración. Apache puede utilizar el `.htaccess` incluido, siempre con `DocumentRoot` en `public`.

## Datos, identidad y servicios

1. Copia el paquete privado por SFTP/SSH a `storage/app/private/imports` con permisos restringidos.
2. Importa perfiles, publicaciones y correos en el orden indicado en el README.
3. Abre `/admin/instalacion` con el administrador y configura los servicios o conserva los valores iniciales de `.env`. Copia los logotipos iniciales a `public/brand`, o súbelos desde Administración → Identidad y logotipos.
4. Configura Google con el callback exacto de producción. El registro sigue limitado a los dominios de servidor; una cuenta autorizada por Google no evita esa comprobación.
5. Configura Turnstile con el dominio real. No uses claves de prueba en producción.
6. Configura SMTP y comprueba la recuperación con una cuenta propia. El entorno local usa registro de correo; no es una comprobación de entrega SMTP.
7. Configura el bucket privado y ejecuta `php artisan catalog:storage-check`. Si se conservan bucket y objetos no necesitas volver a cargarlos. Si cambia el proveedor, copia los objetos conservando sus rutas y verifica las autorizaciones.
8. Revisa aviso institucional, contacto, cookies, plazos, proveedores y derechos antes de poner el sitio a disposición del público.

Las notificaciones de recuperación se envían con el transporte de correo configurado. El sistema base no necesita un bot ni un recolector activo. Si agregas trabajos de cola o recolección posteriormente, instala su worker/scheduler y usa un flujo de revisión para candidatos externos.

## Respaldos y actualizaciones

Respalda la base de Laravel, `.env`/`APP_KEY` por canal privado y los objetos de todos los almacenamientos utilizados. Las conexiones guardadas en el panel están cifradas en la base: conserva la misma clave al restaurar. Prueba una restauración en otra base antes de sustituir una instalación. No publiques los respaldos: contienen usuarios, correos, historial y rutas de documentos.

Antes de actualizar, crea un respaldo; descarga el código, instala dependencias bloqueadas, compila, ejecuta migraciones normales con `--force` y actualiza cachés. La importación periódica conserva ediciones del investigador, pero no sustituye un plan de respaldo. Verifica permisos después de los cambios.

## WSL desde PowerShell

Mantén el proyecto en el sistema de archivos Linux para evitar lentitud y permisos heredados de `/mnt/c`:

```powershell
wsl -d Ubuntu -- bash -lc 'cd /home/reyes/proyectos/investigacion && php artisan serve --host=127.0.0.1 --port=8000'
```

Desde Windows puedes abrir la carpeta con `\\wsl.localhost\Ubuntu\home\reyes\proyectos\investigacion`. Los paquetes se instalan dentro de WSL, no con PHP/Node de Windows. La vista previa queda limitada al equipo local. El entorno de producción debe usar Nginx/Apache y PHP-FPM con HTTPS.
