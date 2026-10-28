<?php

namespace App\Http\Middleware;

use App\Services\RuntimeConfiguration;
use Closure;

class RuntimeSettings
{
    public function handle($r, Closure $next)
    {
        app(RuntimeConfiguration::class)->apply();

        return $next($r);
    }
}
