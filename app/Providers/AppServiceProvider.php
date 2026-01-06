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
        $this->app->singleton('wallet', function ($app) {
            return new WalletService();
        });
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        User::observe(UserObserver::class);
    }
}
