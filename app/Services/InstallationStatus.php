<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstallationStatus
{
    public function checks(): array
    {
        $url = parse_url(config('app.url'));
        $disk = config('filesystems.disks.'.config('filesystems.default'), []);

        return [
            ['name' => 'Base de datos', 'ready' => (bool) DB::connection()->getPdo(), 'detail' => 'Conexión activa y migraciones aplicadas.'],
            ['name' => 'Recursos de la interfaz', 'ready' => is_file(public_path('build/manifest.json')), 'detail' => 'Se generan con npm ci y npm run build.'],
            ['name' => 'Dominio y acceso institucional', 'ready' => ! empty($url['host']) && ! empty(config('catalogue.domains')), 'detail' => 'Revisa la URL, HTTPS y los dominios de correo permitidos.'],
            ['name' => 'Google', 'ready' => (bool) (config('services.google.client_id') && config('services.google.client_secret')), 'detail' => 'Requiere un cliente OAuth y registrar el callback en Google Cloud.'],
            ['name' => 'Correo saliente', 'ready' => config('mail.default') === 'smtp' ? ! empty(config('mail.mailers.smtp.host')) : (config('mail.default') === 'sendmail' && is_executable('/usr/sbin/sendmail')), 'detail' => config('mail.default') === 'log' ? 'Modo de registro local: no se entregan correos.' : 'Comprueba el envío a tu propia cuenta con el botón de prueba.'],
            ['name' => 'Almacenamiento', 'ready' => ($disk['driver'] ?? null) === 'local' || (! empty($disk['bucket']) && ! empty($disk['key']) && ! empty($disk['secret'])), 'detail' => 'Los documentos conservan el almacenamiento con el que se subieron. Prueba el backend activo antes de utilizarlo.'],
            ['name' => 'Turnstile', 'ready' => (bool) (config('catalogue.turnstile_enabled') && config('catalogue.turnstile_site_key') && config('catalogue.turnstile_secret_key')), 'detail' => 'Las claves definitivas y el hostname deben coincidir; solo el entorno local permite desactivarlo.'],
            ['name' => 'Aviso de privacidad', 'ready' => (bool) config('catalogue.privacy_url'), 'detail' => 'La institución debe revisar aviso, contacto, conservación y proveedores antes de publicar.'],
        ];
    }
}
