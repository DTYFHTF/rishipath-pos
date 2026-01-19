<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Events\Login;

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
        // Update user's last_login_at timestamp when they login
        Event::listen(Login::class, function (Login $event) {
            try {
                $event->user?->update(['last_login_at' => now()]);
            } catch (\Throwable $e) {
                // swallow any unexpected errors during login timestamp update
            }
        });
    }
}
