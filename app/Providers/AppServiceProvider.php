<?php

namespace App\Providers;

use App\Models\ProductBatch;
use App\Observers\ProductBatchObserver;
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
        // Register ProductBatch observer to auto-sync stock levels
        ProductBatch::observe(ProductBatchObserver::class);

        // Update user's last_login_at timestamp when they login
        Event::listen(Login::class, function (Login $event) {
            try {
                $event->user?->update(['last_login_at' => now()]);
            } catch (\Throwable $e) {
                // swallow any unexpected errors during login timestamp update
            }
        });

        // Register Blade directives for pricing
        \Illuminate\Support\Facades\Blade::directive('price', function ($expression) {
            return "<?php echo \\App\\Services\\PricingService::formatPrice({$expression}, auth()->user()?->organization); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('currency', function () {
            return "<?php echo \\App\\Services\\PricingService::getCurrencySymbol(auth()->user()?->organization); ?>";
        });
    }
}