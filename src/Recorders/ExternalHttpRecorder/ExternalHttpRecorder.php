<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder;

use Illuminate\Http\Client\Factory as HttpFactory;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\ExternalHttpRecorder as BaseExternalHttpRecorder;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Tracer;

class ExternalHttpRecorder extends BaseExternalHttpRecorder
{
    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected HttpFactory $httpFactory,
        array $config,
        Redactor $redactor,
    ) {
        parent::__construct($tracer, $backTracer, $config, $redactor);
    }

    public function boot(): void
    {
        $this->httpFactory->globalMiddleware(new FlareMiddleware(app(Flare::class)));
    }
}
