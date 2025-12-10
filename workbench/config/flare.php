<?php

use Spatie\FlareClient\Api;
use Spatie\LaravelFlare\AttributesProviders\LaravelUserAttributesProvider;
use Spatie\LaravelFlare\FlareConfig;
use Workbench\App\Senders\FileSender;

return [
    'key' => 'fake-api-key-for-testing-only',

    'collects' => FlareConfig::defaultCollects(
        ignore: [],
        extra: []
    ),

    'sender' => [
        'class' => FileSender::class,
        'config' => [
            'timeout' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report
    |--------------------------------------------------------------------------
    |
    | Flare reports errors and exceptions happening within your application.
    |
    */

    'report' => true,

    'trace' => true,

    'sampler' => [
        'class' => \Spatie\FlareClient\Sampling\AlwaysSampler::class,
        'config' => [],
    ],

    'log' => true,
];
