<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Services\WalletService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Correct way: Let Laravel resolve dependencies automatically
        $this->app->singleton('wallet', function ($app) {
            return $app->make(WalletService::class);
            // OR simply: return resolve(WalletService::class);
        });

        // Alternative (cleaner): Just bind as singleton directly
        // $this->app->singleton(WalletService::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        User::observe(UserObserver::class);
    }
}
