<?php

namespace App\Providers;

use App\Http\Controllers\Api\Admin\StorefrontSettingsController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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

        View::composer('layouts.app', function ($view): void {
            $groupLinks = StorefrontSettingsController::defaultGroupLinks();
            $marqueeSpeedSeconds = (float) config('scak.storefront.marquee_speed_seconds', 9.6);

            try {
                if (Schema::hasTable('app_settings')) {
                    $groupLinks = StorefrontSettingsController::groupLinks();
                    $marqueeSpeedSeconds = StorefrontSettingsController::marqueeSpeedSeconds();
                }
            } catch (\Throwable) {
                // Keep storefront rendering even during early boot/migration windows.
            }

            $view->with('storefrontGroupLinks', $groupLinks);
            $view->with('storefrontMarqueeSpeedSeconds', $marqueeSpeedSeconds);
        });
    }
}
