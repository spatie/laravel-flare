<?php

namespace Spatie\LaravelFlare\Recorders\ApplicationRecorder;

use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Routing\Events\PreparingResponse;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Events\Routing;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\Duration;
use Spatie\FlareClient\Tracer;
use Spatie\LaravelFlare\AttributesProviders\LaravelRequestAttributesProvider;
use Spatie\LaravelFlare\Enums\SpanType;

class ApplicationRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Application;
    }

    public function __construct(
        Tracer $tracer,
        protected Application $app,
        protected Dispatcher $dispatcher,
    ) {
        $this->tracer = $tracer;
        $this->trace = true;
        $this->report = false;
    }

    public function start(): void
    {
//        $this->dispatcher->listen('*', function ($e) {
//            ray($e);
//        });

        $this->dispatcher->listen(Routing::class, function (Routing $event) {
            $this->startSpan(function () {
                return Span::build(
                    $this->tracer->currentTraceId(),
                    "Laravel Request",
                    parentId: $this->tracer->currentSpanId(),
                    attributes: [
                        'flare.span_type' => SpanType::Request,
                    ]
                );
            });

            $this->startSpan(function () {
                return Span::build(
                    $this->tracer->currentTraceId(),
                    "Routing",
                    parentId: $this->tracer->currentSpanId(),
                    attributes: [
                        'flare.span_type' => SpanType::Routing,
                    ]
                );
            });
        });

        $this->dispatcher->listen(RouteMatched::class, function (RouteMatched $event) {
            $this->endSpan();
        });

        $this->dispatcher->listen(PreparingResponse::class, fn ($e) => ray($e));
        $this->dispatcher->listen(ResponsePrepared::class, fn ($e) => ray($e));
        $this->dispatcher->listen(RequestHandled::class, function (RequestHandled $event) {
            $this->endSpan(
                attributes: (new LaravelRequestAttributesProvider())->toArray($event->request)
            );
        });
        $this->dispatcher->listen('bootstrapped: *', fn ($e) => ray($e));


        $this->dispatcher->listen(Terminating::class, [$this, 'recordTerminate']);
        $this->app->booted(fn () => $this->recordBooted());

        $this->recordStart();
    }

    public function recordStart(): void
    {
        $this->startSpan(function () {
            return Span::build(
                $this->tracer->currentTraceId(),
                "Laravel Application",
                start: defined('LARAVEL_START') ? Duration::phpMicroTime(LARAVEL_START) : null,
                parentId: $this->tracer->currentSpanId(),
                attributes: [
                    'flare.span_type' => SpanType::Application,
                ]
            );
        });

        $this->startSpan(function () {
            return Span::build(
                $this->tracer->currentTraceId(),
                "Laravel Booting",
                start: defined('LARAVEL_START') ? Duration::phpMicroTime(LARAVEL_START) : null,
                parentId: $this->tracer->currentSpanId(),
                attributes: [
                    'flare.span_type' => SpanType::Booting,
                ]
            );
        });
    }

    public function recordBooted(): ?Span
    {
        return $this->endSpan();
    }

    public function recordTerminate(): ?Span
    {
        return $this->endSpan();
    }

    protected function canStartTraces(): bool
    {
        return true;
    }
}
