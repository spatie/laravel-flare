<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle;

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware as BaseFlareMiddleware;

/** @deprecated External HTTP requests are recorded automatically, registering this middleware records every request twice. */
class FlareMiddleware extends BaseFlareMiddleware
{
    public function __construct()
    {
        parent::__construct(app(Flare::class));
    }
}
