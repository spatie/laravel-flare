<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;

uses(ConfigureFlare::class);

beforeEach(function () {
    config()->set('logging.channels.flare.driver', 'flare');
    config()->set('logging.default', 'flare');

    FakeTime::setup('2019-01-01 12:34:56');
});

it('it does not report exceptions using the flare api via logger', function () {
    setupFlare(handleErrorsWithFlare: false);

    Route::get('exception', fn () => nonExistingFunction());

    $this
        ->get('/exception')
        ->assertStatus(500);

    FakeSender::instance()->assertRequestsSent(0);
});

it('does not report normal log messages', function () {
    setupFlare();

    Log::info('this is a log message');
    Log::debug('this is a log message');

    FakeSender::instance()->assertRequestsSent(0);
});

it('reports log messages above the specified minimum level', function () {
    setupFlare();

    Log::error('this is a log message');
    Log::emergency('this is a log message');
    Log::critical('this is a log message');

    FakeSender::instance()->assertRequestsSent(3);
});

it('reports different log levels when configured', function () {
    setupFlare(fn (FlareConfig $config) => $config->sendLogsAsEvents(minimumReportLogLevel: Level::Debug));

    Log::debug('this is a log message');
    Log::error('this is a log message');
    Log::emergency('this is a log message');
    Log::critical('this is a log message');

    FakeSender::instance()->assertRequestsSent(4);
});

it('can log null values', function () {
    setupFlare();

    Log::info(null);
    Log::debug(null);
    Log::error(null);
    Log::emergency(null);
    Log::critical(null);

    FakeSender::instance()->assertRequestsSent(3);
});

it('adds log messages to the report', function () {
    setupFlare();

    Route::get('exception', function () {
        Log::info('info log');
        Log::debug('debug log');
        Log::notice('notice log');

        nonExistingFunction();
    });

    $this->get('/exception');

    FakeSender::instance()->assertRequestsSent(1);

    $arguments = FakeSender::instance()->getLastPayload();

    $loggedEvents = array_values(array_filter(
        $arguments['events'],
        fn (array $event) => $event['type'] === SpanEventType::Log && in_array(
            $event['attributes']['log.message'],
            ['info log', 'debug log', 'notice log']
        ),
    )); // Remove all logs from other packages

    expect($loggedEvents)
        ->toHaveCount(3)
        ->each
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Log);

    expect($loggedEvents[0]['attributes'])
        ->toHaveKey('log.level', 'info')
        ->toHaveKey('log.message', 'info log')
        ->toHaveKey('log.context', []);

    expect($loggedEvents[1]['attributes'])
        ->toHaveKey('log.level', 'debug')
        ->toHaveKey('log.message', 'debug log')
        ->toHaveKey('log.context', []);

    expect($loggedEvents[2]['attributes'])
        ->toHaveKey('log.level', 'notice')
        ->toHaveKey('log.message', 'notice log')
        ->toHaveKey('log.context', []);
});

it('can disable sending logs as a report but keep them as span events in an exception report', function ($logLevel) {
    setupFlare(fn (FlareConfig $config) => $config->sendLogsAsEvents(false));

    Log::log($logLevel, 'log');

    Route::get('exception', function () {
        nonExistingFunction();
    });

    $this->get('/exception');

    FakeSender::instance()->assertRequestsSent(1);

    $arguments = FakeSender::instance()->getLastPayload();

    expect($arguments['exceptionClass'])->toBe('Error');
    expect($arguments['message'])->toBe('Call to undefined function nonExistingFunction()');

    expect($arguments['events'])
        ->toHaveCount(1)
        ->each
        ->toHaveKey('startTimeUnixNano', 1546346096000000000)
        ->toHaveKey('endTimeUnixNano', null)
        ->toHaveKey('type', SpanEventType::Log);

    expect($arguments['events'][0]['attributes'])
        ->toHaveKey('log.level', $logLevel)
        ->toHaveKey('log.message', 'log')
        ->toHaveKey('log.context', []);
})->with('provideMessageLevels');

it('it will report an exception with log span events with metadata', function () {
    setupFlare(fn (FlareConfig $config) => $config->sendLogsAsEvents(false));

    Log::info('log', [
        'meta' => 'data',
    ]);

    Route::get('exception', function () {
        nonExistingFunction();
    });

    $this->get('/exception');

    FakeSender::instance()->assertRequestsSent(1);

    $arguments = FakeSender::instance()->getLastPayload();

    expect($arguments['exceptionClass'])->toBe('Error');
    expect($arguments['message'])->toBe('Call to undefined function nonExistingFunction()');

    expect($arguments['events'][0]['attributes'])->toHaveKey('log.context', ['meta' => 'data']);
});

// Datasets
dataset('provideMessageLevels', [
    ['info'],
    ['notice'],
    ['debug'],
    ['warning'],
    ['error'],
    ['critical'],
    ['emergency'],
]);
