<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Facades\Flare;
use Spatie\LaravelFlare\FlareConfig;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use function Pest\Laravel\freezeTime;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config()->set('logging.channels.flare.driver', 'flare');
    config()->set('logging.default', 'flare');

    $datetime = new DateTimeImmutable('2019-01-01 12:34:56');

    FakeTime::setup($datetime);
});

it('it does not report exceptions using the flare api via logger', function () {
    setupFlare(fn(FlareConfig $config) => $config->report(false));

    Route::get('exception', fn () => nonExistingFunction());

    $this
        ->get('/exception')
        ->assertStatus(500);

    FakeApi::assertNothingSent();
});

it('does report log messages', function () {
    $flare = setupFlare();

    Log::debug('this is a log message');
    Log::info('this is a log message');
    Log::notice('this is a log message');
    Log::warning('this is a log message');
    Log::error('this is a log message');
    Log::critical('this is a log message');
    Log::alert('this is a log message');
    Log::emergency('this is a log message');

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    $lastLog = FakeApi::lastLog()->expectLogCount(8);

    $lastLog->expectLog(0)->expectSeverityText('debug');
    $lastLog->expectLog(1)->expectSeverityText('info');
    $lastLog->expectLog(2)->expectSeverityText('notice');
    $lastLog->expectLog(3)->expectSeverityText('warning');
    $lastLog->expectLog(4)->expectSeverityText('error');
    $lastLog->expectLog(5)->expectSeverityText('critical');
    $lastLog->expectLog(6)->expectSeverityText('alert');
    $lastLog->expectLog(7)->expectSeverityText('emergency');

});

it('reports log messages above the specified minimum level', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->log(minimalLevel: Level::Critical));

    Log::error('this is a log message');
    Log::emergency('this is a log message');
    Log::critical('this is a log message');

    $flare->logger->flush();

    FakeApi::assertSent(logs: 1);

    $lastLog = FakeApi::lastLog()->expectLogCount(2);

    $lastLog->expectLog(0)->expectSeverityText('emergency');
    $lastLog->expectLog(1)->expectSeverityText('critical');
});

it('can log null values', function () {
    $flare = setupFlare();

    Log::info(null);

    $flare->logger->flush();

    FakeApi::lastLog()
        ->expectLogCount(1)
        ->expectLog(0)
        ->expectBody('');
});

it('adds log messages to the report', function () {
    setupFlare();

    Route::get('exception', function () {
        Log::info('info log');
        Log::debug('debug log'); // Minimal level for error logs is info
        Log::notice('notice log', ['some' => 'context']);

        nonExistingFunction();
    });

    $this->get('/exception');

    $lastReport = FakeApi::lastReport()->expectEventCount(2);

    $lastReport->expectEvent(0)
        ->expectMissingEnd()
        ->expectType(SpanEventType::Log)
        ->expectAttribute('log.message', 'info log')
        ->expectAttribute('log.level', 'info')
        ->expectAttribute('log.context', []);

    $lastReport->expectEvent(1)
        ->expectMissingEnd()
        ->expectType(SpanEventType::Log)
        ->expectAttribute('log.message', 'notice log')
        ->expectAttribute('log.level', 'notice')
        ->expectAttribute('log.context', ['some' => 'context']);
});

it('it can raise the minimal level of logs added to reports', function () {
    setupFlare(fn(FlareConfig $config) => $config->collectLogsWithErrors(minimalLevel: Level::Notice) );

    Route::get('exception', function () {
        Log::info('info log');
        Log::debug('debug log');
        Log::notice('notice log', ['some' => 'context']);

        nonExistingFunction();
    });

    $this->get('/exception');

    $lastReport = FakeApi::lastReport()->expectEventCount(1);

    $lastReport->expectEvent(0)
        ->expectMissingEnd()
        ->expectType(SpanEventType::Log)
        ->expectAttribute('log.message', 'notice log')
        ->expectAttribute('log.level', 'notice')
        ->expectAttribute('log.context', ['some' => 'context']);
});

it('it can raise the minimal level of logs added to reports but the logger minimal level is king', function () {
    setupFlare(fn(FlareConfig $config) => $config->collectLogsWithErrors(minimalLevel: Level::Info)->log(minimalLevel: Level::Notice));

    Route::get('exception', function () {
        Log::info('info log'); // This will not be logged because the logger minimal level is notice
        Log::debug('debug log');
        Log::notice('notice log', ['some' => 'context']);

        nonExistingFunction();
    });

    $this->get('/exception');

    $lastReport = FakeApi::lastReport()->expectEventCount(1);

    $lastReport->expectEvent(0)
        ->expectMissingEnd()
        ->expectType(SpanEventType::Log)
        ->expectAttribute('log.message', 'notice log')
        ->expectAttribute('log.level', 'notice')
        ->expectAttribute('log.context', ['some' => 'context']);
});

it('can set a max amount of log items added to reports and always keeps the latest ones', function () {
    setupFlare(fn(FlareConfig $config) => $config->collectLogsWithErrors(maxItems: 3));

    Route::get('exception', function () {
        Log::info('info log 1');
        Log::info('info log 2');
        Log::info('info log 3');
        Log::info('info log 4');
        Log::info('info log 5');

        nonExistingFunction();
    });

    $this->get('/exception');

    $lastReport = FakeApi::lastReport()->expectEventCount(3);

    $lastReport->expectEvent(0)->expectAttribute('log.message', 'info log 3');
    $lastReport->expectEvent(1)->expectAttribute('log.message', 'info log 4');
    $lastReport->expectEvent(2)->expectAttribute('log.message', 'info log 5');
});

it('will never store logs as trace events', function (){
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->tracer->startSpan('Test Span');

    Log::info('info log');

    $flare->tracer->endSpan();
    $flare->tracer->endTrace();

    FakeApi::assertSent(traces: 1);

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectSpanEventCount(0);
});
