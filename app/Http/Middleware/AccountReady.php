<?php

namespace App\Http\Middleware;

use Closure;

class AccountReady
{
    public function handle($r, Closure $next)
    {
        abort_unless($r->user()?->active, 403, 'Cuenta desactivada.');
        if ($r->user()->must_change_password && ! $r->routeIs('password.change', 'password.update', 'logout')) {
            return redirect()->route('password.change');
        }

return $next($r);
    }
}
