<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ...

    protected $middlewareGroups = [
    'web' => [
        // default web middlewares...
    ],

    'api' => [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        // Add CORS middleware
        \App\Http\Middleware\CorsMiddleware::class,
    ],
];

    protected $routeMiddleware = [
        // baaki middlewares...
        'canManageAppointment' => \App\Http\Middleware\CanManageAppointment::class,

    ];

    // ...
}
