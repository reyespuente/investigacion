<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Branding
{
    public function handle($r, Closure $next)
    {
        $settings = Cache::remember('institution_branding', 300, fn () => DB::table('site_settings')->whereIn('key', ['institution', 'short_name', 'portal_name', 'network_name', 'primary_color', 'accent_color', 'institution_logo_path', 'network_logo_path', 'institution_logo_disk', 'network_logo_disk', 'logo_version'])->pluck('value', 'key')->all());
        foreach (['institution', 'short_name', 'portal_name', 'network_name', 'primary_color', 'accent_color', 'institution_logo_path', 'network_logo_path', 'institution_logo_disk', 'network_logo_disk', 'logo_version'] as $key) {
            if (array_key_exists($key, $settings)) {
                config(['catalogue.'.$key => $settings[$key]]);
            }
        }foreach (['primary_color' => '#1B396A', 'accent_color' => '#117533'] as $key => $default) {
            if (! preg_match('/^#[a-f0-9]{6}$/i', config('catalogue.'.$key))) {
                config(['catalogue.'.$key => $default]);
            }
        }

return $next($r);
    }
}
