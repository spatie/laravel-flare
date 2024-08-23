<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Flare;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Spatie\LaravelFlare\Tests\TestClasses\FakeArgumentsReducer;

uses(ConfigureFlare::class);

beforeEach(function () {
    ini_set('zend.exception_ignore_args', 0); // Enabled on GH actions

    app()->singleton(FlareConfig::class, fn () => FlareConfig::make('fake')->stackArguments(false));
});

it('can reduce a collection', function () {
    $flare = setupFlare();

    function collectionException(Collection $collection)
    {
        return new Exception('Whoops');
    }

    $report = $flare->report(collectionException(collect(['a', 'b', 'nested' => ['c', 'd']])));

    expect($report->toArray()['stacktrace'][1]['arguments'][0])->toBe([
        'name' => 'collection',
        'value' => ['a', 'b', 'nested' => 'array (size=2)'],
        'original_type' => Collection::class,
        'passed_by_reference' => false,
        'is_variadic' => false,
        'truncated' => false,
    ]);
});

it('can reduce a model', function () {
    $flare = setupFlare();

    $user = new User();
    $user->id = 10;

    function userException(User $user)
    {
        return new Exception('Whoops');
    }

    $report = app(Flare::class)->report(userException($user));

    expect($report->toArray()['stacktrace'][1]['arguments'][0])->toBe([
        'name' => 'user',
        'value' => 'id:10',
        'original_type' => User::class,
        'passed_by_reference' => false,
        'is_variadic' => false,
        'truncated' => false,
    ]);
});

it('can disable the use of arguments', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addStackFrameArguments(withStackFrameArguments: false));

    function exceptionWithArgumentsDisabled(string $string)
    {
        return new Exception('Whoops');
    }

    $report = $flare->report(exceptionWithArgumentsDisabled('Hello World'));

    expect($report->toArray()['stacktrace'][1]['arguments'])->toBeNull();
});

it('can set a custom arguments reducer', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->addStackFrameArguments(argumentReducers: ArgumentReducers::create([
        FakeArgumentsReducer::class,
    ])));

    function exceptionWithCustomArgumentReducer(string $string)
    {
        return new Exception('Whoops');
    }

    $report = $flare->report(exceptionWithCustomArgumentReducer('Hello World'));

    expect($report->toArray()['stacktrace'][1]['arguments'][0]['value'])->toBe('FAKE');
});
