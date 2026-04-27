<?php

namespace App\Providers;

use App\Models\FraudFlag;
use App\Models\KycRecord;
use App\Models\SystemSetting;
use App\Policies\FraudFlagPolicy;
use App\Policies\KycRecordPolicy;
use App\Policies\SystemSettingPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        Gate::policy(KycRecord::class, KycRecordPolicy::class);
        Gate::policy(FraudFlag::class, FraudFlagPolicy::class);
        Gate::policy(SystemSetting::class, SystemSettingPolicy::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $testing = app()->environment('testing');

        RateLimiter::for('api-login', function (Request $request) use ($testing) {
            $perMinute = $testing ? 1000 : 20;

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        RateLimiter::for('api-register', function (Request $request) use ($testing) {
            $perMinute = $testing ? 1000 : 10;

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        RateLimiter::for('api-lookup', function (Request $request) use ($testing) {
            $perMinute = $testing ? 10000 : 60;

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        /** Send, QR pay, checkout, buy-for-me fulfill, single-product buy */
        RateLimiter::for('api-money-write', function (Request $request) use ($testing) {
            $perMinute = $testing ? 100000 : 120;

            return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('api-rewards-checkin', function (Request $request) use ($testing) {
            $perMinute = $testing ? 10000 : 45;

            return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()));
        });

        RateLimiter::for('api-buy-request-create', function (Request $request) use ($testing) {
            $perMinute = $testing ? 10000 : 30;

            return Limit::perMinute($perMinute)->by((string) ($request->user()?->id ?? $request->ip()));
        });
    }
}
