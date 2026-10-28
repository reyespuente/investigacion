<?php

namespace App\Http\Controllers;

use App\Services\Audit;
use App\Services\InstallationStatus;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SystemSettingsController extends Controller
{
    private function administrator(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403);
    }

    public function edit(RuntimeConfiguration $settings, InstallationStatus $status)
    {
        $this->administrator();

        return view('admin.system', ['settings' => $settings->safeValues(), 'checks' => $status->checks()]);
    }

    public function setup(RuntimeConfiguration $settings, InstallationStatus $status)
    {
        $this->administrator();

        return view('admin.setup', ['settings' => $settings->safeValues(), 'checks' => $status->checks()]);
    }

    public function update(Request $r, RuntimeConfiguration $runtime)
    {
        $this->administrator();
        $d = $r->validate([
            'current_password' => 'required|current_password', 'app_url' => 'required|url:http,https|max:2048', 'domains' => 'required|string|max:2000', 'privacy_url' => 'nullable|url:https|max:2048', 'privacy_contact' => 'nullable|email|max:254',
            'mail_mode' => 'required|in:smtp,sendmail,log', 'mail_host' => 'nullable|string|max:250', 'mail_port' => 'nullable|integer|between:1,65535', 'mail_scheme' => 'required|in:smtp,smtps', 'mail_username' => 'nullable|string|max:500', 'mail_password' => 'nullable|string|max:2000', 'mail_from_address' => 'required|email|max:254', 'mail_from_name' => 'required|string|max:250',
            'storage_mode' => 'required|in:local,s3', 's3_endpoint' => 'nullable|url:https|max:2048', 's3_region' => 'nullable|string|max:100', 's3_bucket' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9][a-z0-9.\-]*[a-z0-9]$/'], 's3_key' => 'nullable|string|max:500', 's3_secret' => 'nullable|string|max:2000', 's3_use_path_style_endpoint' => 'nullable|boolean',
            'google_client_id' => 'nullable|string|max:500', 'google_client_secret' => 'nullable|string|max:2000', 'turnstile_enabled' => 'nullable|boolean', 'turnstile_site_key' => 'nullable|string|max:500', 'turnstile_secret_key' => 'nullable|string|max:2000', 'turnstile_hostname' => 'nullable|string|max:253',
        ]);
        $url = parse_url($d['app_url']);
        if (isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || ! in_array($url['path'] ?? '', ['', '/'], true) || ($url['scheme'] !== 'https' && ! (app()->environment(['local', 'testing']) && in_array($url['host'], ['localhost', '127.0.0.1', '[::1]'], true)))) {
            throw ValidationException::withMessages(['app_url' => 'Usa la URL raíz HTTPS del portal, sin usuario, consulta ni ruta. HTTP solo se admite en localhost durante desarrollo.']);
        }
        $domains = array_values(array_unique(array_filter(array_map(fn ($v) => strtolower(trim($v)), explode(',', $d['domains'])))));
        foreach ($domains as $domain) {
            if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($domain, '.') || str_contains($domain, '*')) {
                throw ValidationException::withMessages(['domains' => 'Escribe dominios exactos separados por comas, sin @ ni comodines.']);
            }
        }if (! $domains) {
            throw ValidationException::withMessages(['domains' => 'Indica al menos un dominio permitido.']);
        }
        if ($d['mail_mode'] === 'smtp' && empty($d['mail_host'])) {
            throw ValidationException::withMessages(['mail_host' => 'Indica el servidor SMTP.']);
        }
        if (app()->environment('production') && ! $r->boolean('turnstile_enabled')) {
            throw ValidationException::withMessages(['turnstile_enabled' => 'Turnstile debe permanecer activo en producción.']);
        }
        $values = collect($d)->only(['privacy_url', 'privacy_contact', 'mail_mode', 'mail_host', 'mail_port', 'mail_scheme', 'mail_from_address', 'mail_from_name', 'google_client_id', 'turnstile_site_key'])->all();
        $values['app_url'] = rtrim($d['app_url'], '/');
        $values['domains'] = $domains;
        $values['google_redirect'] = $values['app_url'].'/auth/google/callback';
        $values['turnstile_enabled'] = $r->boolean('turnstile_enabled');
        $values['turnstile_hostname'] = ($d['turnstile_hostname'] ?? null) ?: $url['host'];
        foreach (RuntimeConfiguration::SECRETS as $key) {
            if (isset($d[$key]) && $d[$key] !== '') {
                $values[$key] = $d[$key];
            }
        }
        DB::transaction(function () use ($d, $r, $runtime, $values) {
            $disk = 'local';
            if ($d['storage_mode'] === 's3') {
                $current = config('filesystems.disks.'.config('filesystems.default'), []);
                if (($current['driver'] ?? null) !== 's3') {
                    $current = config('filesystems.disks.s3', []);
                }
                $connection = ['driver' => 's3', 'key' => ($d['s3_key'] ?? null) ?: ($current['key'] ?? ''), 'secret' => ($d['s3_secret'] ?? null) ?: ($current['secret'] ?? ''), 'region' => ($d['s3_region'] ?? null) ?: ($current['region'] ?? ''), 'bucket' => ($d['s3_bucket'] ?? null) ?: ($current['bucket'] ?? ''), 'endpoint' => ($d['s3_endpoint'] ?? null) ?: ($current['endpoint'] ?? null), 'use_path_style_endpoint' => $r->boolean('s3_use_path_style_endpoint'), 'throw' => true, 'report' => false];
                if (! $connection['key'] || ! $connection['secret'] || ! $connection['bucket'] || ! $connection['region']) {
                    throw ValidationException::withMessages(['s3_bucket' => 'Completa bucket, región y credenciales para el almacenamiento S3.']);
                }
                if ($connection['endpoint'] && (isset(parse_url($connection['endpoint'])['user']) || isset(parse_url($connection['endpoint'])['pass']))) {
                    throw ValidationException::withMessages(['s3_endpoint' => 'El endpoint no debe contener credenciales en la URL.']);
                }
                $id = (string) Str::uuid();
                DB::table('storage_profiles')->insert(['id' => $id, 'configuration' => Crypt::encryptString(json_encode($connection, JSON_THROW_ON_ERROR)), 'created_at' => now(), 'updated_at' => now()]);
                $disk = 'profile_'.$id;
            }
            $runtime->save($values + ['storage_disk' => $disk]);
            Audit::record('site_settings', 0, 'system_configuration_updated', null, ['fields' => array_keys($values), 'storage_mode' => $d['storage_mode'], 'credentials_changed' => array_values(array_intersect(array_keys($values), RuntimeConfiguration::SECRETS))]);
        });

        return back()->with('success', 'Configuración guardada. Los secretos existentes se conservaron donde dejaste el campo vacío. Prueba correo y almacenamiento antes de utilizarlos.');
    }

    public function testMail(Request $r)
    {
        $this->administrator();
        $r->validate(['current_password' => 'required|current_password']);
        abort_if(config('mail.default') === 'log', 422, 'El modo de registro no entrega correos. Elige SMTP o el servicio local para comprobar la entrega.');
        try {
            Mail::send('emails.notification', ['heading' => 'Prueba de correo del repositorio', 'bodyText' => 'Esta es una comprobación solicitada desde tu panel de administración.', 'action' => 'Abrir el repositorio', 'url' => config('app.url'), 'note' => 'La recepción de este mensaje confirma que el transporte configurado pudo entregar a tu cuenta.'], fn ($m) => $m->to($r->user()->email)->subject('Prueba de correo · '.config('catalogue.portal_name')));
        } catch (\Throwable) {
            return back()->withErrors(['mail' => 'No se pudo completar el envío. Revisa los datos del proveedor, el remitente autorizado y la conexión del servidor.']);
        }Audit::record('site_settings', 0, 'mail_test_requested');

        return back()->with('success', 'El transporte aceptó el mensaje para tu correo de administrador. Comprueba también la bandeja de entrada y spam.');
    }

    public function testStorage(Request $r)
    {
        $this->administrator();
        $r->validate(['current_password' => 'required|current_password']);
        $key = 'repository/connection-tests/'.Str::uuid().'.txt';
        try {
            Storage::put($key, 'Repository connectivity check');
            if (Storage::get($key) !== 'Repository connectivity check') {
                throw new \RuntimeException;
            }
        } catch (\Throwable) {
            return back()->withErrors(['storage' => 'No se pudo verificar el almacenamiento activo. Revisa credenciales, permisos y endpoint.']);
        } finally {
            try {
                Storage::delete($key);
            } catch (\Throwable) {
            }
        }Audit::record('site_settings',0,'storage_test');

        return back()->with('success','Escritura y lectura correctas. Se retiró el objeto temporal exclusivo de comprobación.');
    }
}
