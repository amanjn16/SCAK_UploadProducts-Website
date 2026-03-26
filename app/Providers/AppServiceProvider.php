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
        RateLimiter::for('api', function (Request $request): Limit {
            $identifier = $request->user()?->id ?: $request->ip();
            $maxAttempts = $request->user()?->isAdmin() ? 240 : 120;

            return Limit::perMinute($maxAttempts)->by((string) $identifier);
        });
    }
}
