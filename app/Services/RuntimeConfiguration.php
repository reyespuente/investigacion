<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RuntimeConfiguration
{
    public const MAP = [
        'app_url' => 'app.url', 'domains' => 'catalogue.domains', 'privacy_url' => 'catalogue.privacy_url', 'privacy_contact' => 'catalogue.privacy_contact',
        'mail_mode' => 'mail.default', 'mail_host' => 'mail.mailers.smtp.host', 'mail_port' => 'mail.mailers.smtp.port', 'mail_scheme' => 'mail.mailers.smtp.scheme', 'mail_username' => 'mail.mailers.smtp.username', 'mail_password' => 'mail.mailers.smtp.password', 'mail_from_address' => 'mail.from.address', 'mail_from_name' => 'mail.from.name',
        'storage_disk' => 'filesystems.default', 'google_client_id' => 'services.google.client_id', 'google_client_secret' => 'services.google.client_secret', 'google_redirect' => 'services.google.redirect',
        'turnstile_enabled' => 'catalogue.turnstile_enabled', 'turnstile_site_key' => 'catalogue.turnstile_site_key', 'turnstile_secret_key' => 'catalogue.turnstile_secret_key', 'turnstile_hostname' => 'catalogue.turnstile_hostname',
    ];

    public const SECRETS = ['mail_password', 'mail_username', 'google_client_secret', 'turnstile_secret_key'];

    public function apply(): void
    {
        $rows = Cache::remember('runtime_configuration_encrypted', 60, fn () => DB::table('site_settings')->where('key', 'like', 'runtime.%')->pluck('value', 'key')->all());
        foreach (self::MAP as $key => $config) {
            $name = 'runtime.'.$key;
            if (! array_key_exists($name, $rows)) {
                continue;
            }$value = in_array($key, self::SECRETS, true) ? Crypt::decryptString($rows[$name]) : json_decode($rows[$name], true, 512, JSON_THROW_ON_ERROR);
            config([$config => $value]);
        }
        $profiles = Cache::remember('storage_profiles_encrypted_v2', 60, fn () => DB::table('storage_profiles')->pluck('configuration', 'id')->all());
        foreach ($profiles as $id => $configuration) {
            config(['filesystems.disks.profile_'.$id => json_decode(Crypt::decryptString($configuration), true, 512, JSON_THROW_ON_ERROR)]);
        }
        if (app()->environment('production')) {
            config(['mail.mailers.smtp.require_tls' => true]);
        }
        Mail::purge();
        if (app()->environment('production')) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme('https');
        }
    }

    public function save(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::MAP)) {
                throw new \InvalidArgumentException('Configuración no admitida');
            }$stored = in_array($key, self::SECRETS, true) ? Crypt::encryptString((string) $value) : json_encode($value, JSON_THROW_ON_ERROR);
            DB::table('site_settings')->updateOrInsert(['key' => 'runtime.'.$key], ['value' => $stored, 'created_at' => now(), 'updated_at' => now()]);
        }
        self::forget();
    }

    public static function forget(): void
    {
        foreach (['runtime_configuration_encrypted', 'storage_profiles_encrypted', 'storage_profiles_encrypted_v2', 'institution_branding'] as $key) {
            Cache::forget($key);
        }
    }

    public function safeValues(): array
    {
        $values = [];
        foreach (self::MAP as $key => $path) {
            if (! in_array($key, self::SECRETS, true)) {
                $values[$key] = config($path);
            }
        }
        foreach (self::SECRETS as $key) {
            $values['has_'.$key] = (bool) config(self::MAP[$key]);
        }
        $disk = config('filesystems.disks.'.config('filesystems.default'), []);
        $values['storage_mode'] = $disk['driver'] ?? 'local';
        foreach (['endpoint', 'region', 'bucket', 'use_path_style_endpoint'] as $key) {
            $values['s3_'.$key] = $disk[$key] ?? config('filesystems.disks.s3.'.$key);
        }
        $values['has_s3_credentials'] = ! empty($disk['key']) && ! empty($disk['secret']);
        $values['domains'] = implode(',', config('catalogue.domains'));
        $values['mail_scheme'] = $values['mail_scheme'] ?? 'smtp';

        return $values;
    }
}
