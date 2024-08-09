<?php

use Illuminate\Support\Facades\Context;
use Spatie\LaravelFlare\Facades\Flare;

beforeEach(function () {
    // We need to duplicate the class check here because this runs before the skip check
    class_exists(Context::class) && Context::flush();
})->skip(
    ! class_exists(Context::class),
    'Context facade not available (introduced in Laravel 11)',
);

it('will add context information with an exception', function () {
    Context::add('foo', 'bar');
    Context::addHidden('hidden', 'value');

    $report = Flare::report(new Exception);

    $context = $report->toArray()['attributes'];

    $this->assertArrayHasKey('context.laravel', $context);
    $this->assertArrayHasKey('foo', $context['context.laravel']);
    $this->assertArrayNotHasKey('hidden', $context['context.laravel']);
    $this->assertEquals('bar', $context['context.laravel']['foo']);
});

it('will not add context information with an exception if no context was set', function () {
    $report = Flare::report(new Exception);

    $context = $report->toArray()['attributes'];

    $this->assertArrayNotHasKey('laravel.context', $context);
});

it('will not add context information with an exception if only hidden context was set', function () {
    Context::addHidden('hidden', 'value');

    $report = Flare::report(new Exception);

    $context = $report->toArray()['attributes'];

    $this->assertArrayNotHasKey('laravel.context', $context);
});
