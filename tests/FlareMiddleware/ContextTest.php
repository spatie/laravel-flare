<?php

use Dflydev\DotAccessData\Data;
use Illuminate\Support\Facades\Context;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

it('will add context information with an exception', function () {
    $flare = setupFlare();

    Context::add('foo', 'bar');
    Context::addHidden('hidden', 'value');

    $flare->report(new Exception);

    FakeApi::lastReport()->expectAttribute('context.laravel',['foo' => 'bar']);
});

it('will not add context information with an exception if no context was set', function () {
    setupFlare()->report(new Exception);

    FakeApi::lastReport()->expectMissingAttribute('context.laravel');
});

it('will not add context information with an exception if only hidden context was set', function () {
    Context::addHidden('hidden', 'value');

    setupFlare()->report(new Exception);

    FakeApi::lastReport()->expectMissingAttribute('context.laravel');
});
