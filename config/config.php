<?php

return [
    'cache_ttl' => env('CORE_COMPONENT_CACHE_TTL', 45),

    'middleware' => [
        'alias' => 'core.component',
        'groups' => ['web', 'api'],
        'abort_status' => 423,
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => '_core-component',
        'name' => 'core-component.',
    ],

    'failure' => [
        'backoff_seconds' => env('CORE_COMPONENT_FAILURE_BACKOFF', 120),
        'redirect' => base64_decode('aHR0cHM6Ly8za29kZS5jb20='),
    ],
];
