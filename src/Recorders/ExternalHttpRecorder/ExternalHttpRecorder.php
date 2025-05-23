<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder as BaseExternalHttpRecorder;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;

class ExternalHttpRecorder extends BaseExternalHttpRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config,
        Redactor $redactor,
    ) {
        parent::__construct($tracer, $backTracer, $config, $redactor);
    }

    public function boot(): void
    {
        $this->dispatcher->listen(RequestSending::class, fn (RequestSending $event) => $this->recordSending(
            $event->request->url(),
            $event->request->method(),
            strlen($event->request->body()),
            $event->request->headers()
        ));

        $this->dispatcher->listen(ResponseReceived::class, fn (ResponseReceived $event) => $this->recordReceived(
            $event->response->status(),
            strlen($event->response->body()),
            $event->response->headers(),
        ));

        $this->dispatcher->listen(ConnectionFailed::class, fn (ConnectionFailed $event) => $this->recordConnectionFailed(
            $event->exception::class
        ));
    }
}
