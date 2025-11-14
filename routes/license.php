<?php

use Illuminate\Support\Facades\Route;
use Ps4tek\CoreComponentRepository\CoreComponentRepository;

if (! config('core-component-repository.routes.enabled', true)) {
    return;
}

Route::middleware(config('core-component-repository.middleware.alias', 'core.component'))
    ->prefix(config('core-component-repository.routes.prefix', '_core-component'))
    ->name(config('core-component-repository.routes.name', 'core-component.'))
    ->group(function () {
        Route::get('/heartbeat', function () {
            CoreComponentRepository::initializeCache();

            return response()->json([
                'verified' => CoreComponentRepository::verificationStatus(),
                'signature' => CoreComponentRepository::currentSignature(),
                'timestamp' => now()->timestamp,
            ]);
        })->name('heartbeat');
    });
