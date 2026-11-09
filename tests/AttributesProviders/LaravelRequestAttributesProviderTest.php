<?php

use Illuminate\Http\Request;
use Spatie\FlareClient\Support\Redactor;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

it('returns the url', function () {
    $request = Request::create('/route', 'GET');

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['url.full'])->toBe('http://localhost/route');
});

it('returns the cookies', function () {
    $request = Request::create('/route', 'GET', [], ['cookie' => 'noms']);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.cookies'])->toBe(['cookie' => 'noms']);
});

it('censors body fields inside livewire snapshots', function () {
    $request = livewireUpdateRequest(
        snapshotData: ['password' => 'secret-password', 'label' => 'Alerts'],
    );

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.body.contents']['components'][0]['snapshot'])->toBe([
        'data' => ['password' => '<CENSORED:string>', 'label' => 'Alerts'],
        'memo' => ['name' => 'notification-settings'],
    ]);
});

it('censors livewire updates by their property path', function () {
    $request = livewireUpdateRequest(
        snapshotData: [],
        updates: [
            'password' => 'secret-password',
            'destination.token' => 'secret-token',
            'label' => 'Alerts',
        ],
    );

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password', 'destination']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.body.contents']['components'][0]['updates'])->toBe([
        'password' => '<CENSORED:string>',
        'destination.token' => '<CENSORED:string>',
        'label' => 'Alerts',
    ]);
});

it('leaves snapshots untouched outside livewire requests', function () {
    $request = livewireUpdateRequest(
        snapshotData: ['password' => 'secret-password'],
        livewire: false,
    );

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.body.contents']['components'][0]['snapshot'])->toBeString();
});

function livewireUpdateRequest(array $snapshotData, array $updates = [], bool $livewire = true): Request
{
    $server = ['CONTENT_TYPE' => 'application/json'];

    if ($livewire) {
        $server['HTTP_X_LIVEWIRE'] = '1';
        $server['HTTP_REFERER'] = 'http://localhost/settings';
    }

    return Request::create('/livewire/update', 'POST', server: $server, content: json_encode([
        'components' => [
            [
                'snapshot' => json_encode([
                    'data' => $snapshotData,
                    'memo' => ['name' => 'notification-settings'],
                ]),
                'updates' => $updates,
                'calls' => [],
            ],
        ],
    ]));
}
