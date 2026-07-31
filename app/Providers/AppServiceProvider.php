<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Pagination\Paginator;

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
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        Paginator::useBootstrap();

        Gate::define('view-admin-dashboard', function ($user) {
            return $user->role === 'admin'; // Customize based on your role field
        });

        Gate::define('view-user-dashboard', function ($user) {
            return $user->role === 'user';
        });

        // Master Barang footer totals (harga beli / harga jual)
        Gate::define('view-total-harga', function ($user) {
            return $user->can('view total harga');
        });
    }
}
