<?php

namespace Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\Guzzle;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack as BaseFlareHandlerStack;

class FlareHandlerStack
{
    /**
     * @param (callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>)|null $handler
     *
     * @return HandlerStack<callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>>
     */
    public static function create(?callable $handler = null): HandlerStack
    {
        return BaseFlareHandlerStack::create(app(Flare::class), $handler);
    }
}
