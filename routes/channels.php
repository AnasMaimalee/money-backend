<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => ['auth'], // or auth:api if you use token auth
]);

Broadcast::channel('jobs.admin', function ($user) {
    return $user->hasAnyRole(['superadmin', 'administrator']);
});