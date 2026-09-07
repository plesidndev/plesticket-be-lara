<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['create' => 30, 'upload' => 20, 'submit' => 10, 'change-request' => 5, 'export' => 5] as $action => $limit) {
            RateLimiter::for('catalog-'.$action, fn (Request $request) => Limit::perMinute($limit)->by((string) ($request->user('api')?->id ?? $request->ip())));
        }
    }
}
