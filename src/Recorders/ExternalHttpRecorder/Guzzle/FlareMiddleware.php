<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle;

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware as BaseFlareMiddleware;

class FlareMiddleware extends BaseFlareMiddleware
{
    public function __construct()
    {
        parent::__construct(app(Flare::class));
    }
}
