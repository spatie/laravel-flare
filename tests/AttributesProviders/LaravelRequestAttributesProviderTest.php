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

function livewireUpdateRequest(array $snapshotData, array $updates = []): Request
{
    return Request::create('/livewire/update', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
        'components' => [
            [
                'snapshot' => json_encode([
                    'data' => $snapshotData,
                    'memo' => ['name' => 'notification-settings'],
                    'checksum' => 'abc',
                ]),
                'updates' => $updates,
                'calls' => [],
            ],
        ],
    ]));
}

it('censors body fields inside livewire snapshots', function () {
    $request = livewireUpdateRequest([
        'password' => 'secret-password',
        'destination' => [['token' => 'secret-token', 'channel' => '#general'], ['s' => 'arr']],
        'label' => 'Alerts',
    ]);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password', 'destination']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    $snapshot = json_decode($attributes['http.request.body.contents']['components'][0]['snapshot'], true);

    expect($snapshot['data'])->toBe([
        'password' => '<CENSORED:string>',
        'destination' => '<CENSORED:array>',
        'label' => 'Alerts',
    ]);
    expect($snapshot['memo'])->toBe(['name' => 'notification-settings']);
});

it('censors livewire updates by their property path', function () {
    $request = livewireUpdateRequest([], [
        'password' => 'secret-password',
        'destination.token' => 'secret-token',
        'label' => 'Alerts',
    ]);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password', 'destination']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    expect($attributes['http.request.body.contents']['components'][0]['updates'])->toBe([
        'password' => '<CENSORED:string>',
        'destination.token' => '<CENSORED:array>',
        'label' => 'Alerts',
    ]);
});

it('leaves livewire snapshots without censored fields intact', function () {
    $request = livewireUpdateRequest(['label' => 'Alerts']);

    $attributes = (new LaravelRequestAttributesProvider(
        new Redactor(censorBodyFields: ['password']),
        app(LivewireComponentFinder::class),
        $request,
    ))->toArray();

    $snapshot = json_decode($attributes['http.request.body.contents']['components'][0]['snapshot'], true);

    expect($snapshot['data'])->toBe(['label' => 'Alerts']);
});
