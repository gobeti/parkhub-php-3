<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. Set BROADCAST_CONNECTION
    | in .env to one of: pusher, soketi, log, null.
    |
    | "soketi" is a self-hosted, open-source Pusher-compatible server and the
    | recommended default for ParkHub self-hosted deployments.
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    */

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST', 'api.pusherapp.com'),
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
                'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
            ],
            'client_options' => [],
        ],

        // Soketi — self-hosted, open-source Pusher-compatible server
        // Run: npx @soketi/soketi start
        'soketi' => [
            'driver' => 'pusher',
            'key' => env('SOKETI_APP_KEY', 'parkhub-local'),
            'secret' => env('SOKETI_APP_SECRET', 'parkhub-secret'),
            'app_id' => env('SOKETI_APP_ID', 'parkhub'),
            'options' => [
                'host' => env('SOKETI_HOST', '127.0.0.1'),
                'port' => env('SOKETI_PORT', 6001),
                'scheme' => env('SOKETI_SCHEME', 'http'),
                'encrypted' => false,
                'useTLS' => false,
            ],
            'client_options' => [],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
