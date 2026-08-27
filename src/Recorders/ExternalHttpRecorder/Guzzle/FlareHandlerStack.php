<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle;

use GuzzleHttp\HandlerStack;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack as BaseFlareHandlerStack;

class FlareHandlerStack
{
    /** @return HandlerStack<callable> */
    public static function create(?callable $handler = null): HandlerStack
    {
        return BaseFlareHandlerStack::create(app(Flare::class), $handler);
    }
}
