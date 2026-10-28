<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Turnstile
{
    public function handle(Request $r, Closure $next, string $action = 'export')
    {
        if (! config('catalogue.turnstile_enabled')) {
            abort_unless(app()->environment(['local', 'testing']), 503, 'La verificación de seguridad no está configurada.');

            return $next($r);
        }
        $token = $r->validate(['cf-turnstile-response' => 'required|string|max:2048'])['cf-turnstile-response'];
        abort_unless(config('catalogue.turnstile_secret_key'), 503, 'La verificación de seguridad no está disponible.');
        try {
            $result = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => config('catalogue.turnstile_secret_key'), 'response' => $token, 'remoteip' => $r->ip()])->throw()->json();
        } catch (\Throwable) {
            abort(503, 'No se pudo verificar la solicitud. Intenta nuevamente.');
        }
        abort_unless(($result['success'] ?? false) === true && ($result['action'] ?? null) === $action && ($result['hostname'] ?? null) === config('catalogue.turnstile_hostname'), 422, 'Verificación vencida o inválida. Intenta nuevamente.');

        return $next($r);
    }
}
