<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 🔥 FORCE API AUTH, NOT WEB
        Broadcast::routes([
            'middleware' => ['api', 'auth:api'],
            'prefix' => 'api',
        ]);

        require base_path('routes/channels.php');
    }
}
