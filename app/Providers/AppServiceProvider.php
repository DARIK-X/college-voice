<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by('login:'.$r->ip()), Limit::perMinute(5)->by('email:'.hash('sha256', mb_strtolower((string) $r->input('email'))))]);
        RateLimiter::for('vote', fn (Request $r) => [Limit::perMinute(10)->by('device:'.$r->attributes->get('device_hash'))]);
        RateLimiter::for('results', fn (Request $r) => Limit::perMinute(15000)->by('results:'.$r->ip()));
        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(30)->by('admin:'.($r->user()?->id ?? $r->ip())));
    }
}
