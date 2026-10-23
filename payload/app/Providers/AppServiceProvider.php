<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Services are resolved automatically by Laravel's container.
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            try {
                $view->with('siteSettings', Settings::publicValues());
            } catch (\Throwable) {
                $view->with('siteSettings', Settings::defaults());
            }
        });
    }
}
