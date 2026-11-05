<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('paymongo-reconcile', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(30)
                ->by('paymongo-reconcile:'.($userId ?? $request->ip()))
                ->response(fn (Request $request, array $headers): RedirectResponse => redirect()
                    ->route('clinic.billing.index')
                    ->withErrors([
                        'checkout' => 'You have checked PayMongo too many times. Please wait a minute before trying again.',
                    ]));
        });

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
