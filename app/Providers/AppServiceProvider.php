<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewInstance;

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
        Password::defaults(fn (): Password => Password::min(8)
            ->symbols()
            ->rules(['regex:/[\p{P}\p{S}]/u', 'regex:/[A-Z]/']));

        View::composer('layouts.clinic-notifications', function (ViewInstance $view): void {
            $view->with(
                'clinicUnreadNotificationCount',
                auth()->user()->unreadNotifications()->count(),
            );
        });
    }
}
