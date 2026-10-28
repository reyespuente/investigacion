<?php

namespace App\Providers;

use App\Services\RuntimeConfiguration;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Institution configuration stays in .env; visual overrides live in site_settings.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('pagination');
        Event::listen(JobProcessing::class, fn () => app(RuntimeConfiguration::class)->apply());
    }
}
