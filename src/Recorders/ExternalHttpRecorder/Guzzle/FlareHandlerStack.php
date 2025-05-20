<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle;

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack as BaseFlareHandlerStack;

class FlareHandlerStack extends BaseFlareHandlerStack
{
    public function __construct(?callable $handler = null)
    {
        parent::__construct(app(Flare::class), $handler);
    }
}
