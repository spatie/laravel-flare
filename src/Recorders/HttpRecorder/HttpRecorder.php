<?php

namespace Spatie\LaravelFlare\Recorders\HttpRecorder;

use Illuminate\Events\Dispatcher;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

// TODO: can be put into the base package
class HttpRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        protected Dispatcher $dispatcher,
        array $config
    ) {
        $this->configure($config);
    }

    public static function type(): RecorderType
    {
        return RecorderType::Http;
    }

    protected function canStartTraces(): bool
    {
        return false;
    }

    public function start(): void
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

    public function recordSending(
        string $url,
        string $method,
        int $requestBodySize,
        array $headers = [],
    ): ?Span {
        return $this->startSpan(function () use ($requestBodySize, $method, $url) {
            $parsedUrl = parse_url($url);

            $name = array_key_exists('host', $parsedUrl)
                ? "Http Request - {$parsedUrl['host']}"
                : 'Http Request';

            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                name: $name,
                parentId: $this->tracer->currentSpanId(),
                attributes: [
                    'flare.span_type' => SpanType::HttpRequest,
                    'url.full' => $url,
                    'http.request.method' => $method,
                    'server.address' => $parsedUrl['host'] ?? null,
                    'server.port' => $parsedUrl['port'] ?? null,
                    'url.scheme' => $parsedUrl['scheme'] ?? null,
                    'url.path' => $parsedUrl['path'] ?? null,
                    'url.query' => $parsedUrl['query'] ?? null,
                    'url.fragment' => $parsedUrl['fragment'] ?? null,
                    'http.request.body.size' => $requestBodySize,
                ],
            );
        });
    }

    public function recordReceived(
        int $responseCode,
        int $responseBodySize,
        array $headers = [],
    ): ?Span {
        return $this->endSpan(attributes: [
            'http.response.status_code' => $responseCode,
            'http.response.body.size' => $responseBodySize,
        ]);
    }

    public function recordConnectionFailed(
        string $errorType
    ): ?Span {
        return $this->endSpan(attributes: [
            'error.type' => $errorType,
        ]);
    }
}
