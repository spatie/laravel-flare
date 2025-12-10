<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Orchestra\Testbench\Exceptions\Handler;
use Spatie\FlareClient\Report;
use Spatie\LaravelFlare\Facades\Flare;

it('can see when an exception is handled, meaning it is reported', function () {
    setupFlare();

    $handler = new class(app()) extends Handler {
        public static array $report;

        public function report(Throwable $e)
        {
            self::$report = Flare::report($e)->toArray();
        }
    };

    app()->bind(ExceptionHandler::class, fn () => $handler);

    $someTriggeredException = new Exception('This is a test exception');

    report($someTriggeredException);

    expect($handler::$report)->toBeArray();
    expect($handler::$report)->toHaveKey('handled', true);
});

it('will not mark an exception handled when it is not', function () {
    setupFlare();

    $someTriggeredException = new Exception('This is a test exception');

    $report = Flare::report($someTriggeredException);

    expect($report)->toHaveKey('handled', null);
});
