<?php

namespace Spatie\LaravelFlare\Support;

use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Support\CollectsResolver as BaseCollectsResolver;
use Spatie\LaravelFlare\Enums\LaravelCollectType;
use Spatie\LaravelFlare\FlareMiddleware\AddConsoleInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionContextInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddExceptionHandledStatus;
use Spatie\LaravelFlare\FlareMiddleware\AddJobInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelContext;
use Spatie\LaravelFlare\FlareMiddleware\AddLaravelInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddRequestInformation;
use Spatie\LaravelFlare\FlareMiddleware\AddViewInformation;
use Spatie\LaravelFlare\Recorders\CacheRecorder\CacheRecorder;
use Spatie\LaravelFlare\Recorders\CommandRecorder\CommandRecorder;
use Spatie\LaravelFlare\Recorders\ExternalHttpRecorder\ExternalHttpRecorder;
use Spatie\LaravelFlare\Recorders\FilesystemRecorder\FilesystemRecorder;
use Spatie\LaravelFlare\Recorders\JobRecorder\JobRecorder;
use Spatie\LaravelFlare\Recorders\LogRecorder\LogRecorder;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder;
use Spatie\LaravelFlare\Recorders\QueueRecorder\QueueRecorder;
use Spatie\LaravelFlare\Recorders\RedisCommandRecorder\RedisCommandRecorder;
use Spatie\LaravelFlare\Recorders\RequestRecorder\RequestRecorder;
use Spatie\LaravelFlare\Recorders\RoutingRecorder\RoutingRecorder;
use Spatie\LaravelFlare\Recorders\TransactionRecorder\TransactionRecorder;
use Spatie\LaravelFlare\Recorders\ViewRecorder\ViewRecorder;

class CollectsResolver extends BaseCollectsResolver
{
    protected ?string $requestsMiddlewareClass = null;

    protected function handleUnknownCollectType(FlareCollectType $type, array $options): void
    {
        match ($type) {
            LaravelCollectType::LivewireComponents => $this->livewireComponents($options),
            LaravelCollectType::LaravelInfo => $this->laravelInfo($options),
            LaravelCollectType::LaravelContext => $this->laravelContext($options),
            LaravelCollectType::ExceptionContext => $this->exceptionContext($options),
            LaravelCollectType::HandledExceptions => $this->handledExceptions($options),
            CollectType::Jobs => $this->jobs($options),
            default => null,
        };
    }

    protected function requests(array $options): void
    {
        $middleware = $options['middleware'] ?? AddRequestInformation::class;

        $this->requestsMiddlewareClass = $middleware;
        $this->addMiddleware($middleware);

        $this->addRecorder(RequestRecorder::class);
        $this->addRecorder(RoutingRecorder::class);
    }

    protected function console(array $options): void
    {
        $options['middleware'] ??= AddConsoleInformation::class;
        $options['recorder'] ??= CommandRecorder::class;

        parent::console($options);
    }

    protected function laravelInfo(array $options): void
    {
        $this->addMiddleware(AddLaravelInformation::class, $options);
    }

    protected function laravelContext(array $options): void
    {
        $this->addMiddleware(AddLaravelContext::class, $options);
    }

    protected function exceptionContext(array $options): void
    {
        $this->addMiddleware(AddExceptionContextInformation::class, $options);
    }

    protected function handledExceptions(array $options): void
    {
        $this->addMiddleware(AddExceptionHandledStatus::class, $options);
    }

    protected function jobs(array $options): void
    {
        $this->addRecorder($options['job_recorder'] ?? JobRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
            'max_chained_job_reporting_depth',
        ]));

        $this->addRecorder($options['queue_recorder'] ?? QueueRecorder::class, $this->only($options, [
            'with_traces',
            'with_errors',
            'max_items_with_errors',
        ]));

        $this->addMiddleware($options['middleware'] ?? AddJobInformation::class);
    }

    protected function livewireComponents(
        array $options
    ): void {
        if ($this->requestsMiddlewareClass === null) {
            return;
        }

        $this->middlewares[$this->requestsMiddlewareClass]['include_livewire_components'] = $options['include_livewire_components'] ?? false;
    }

    protected function views(array $options): void
    {
        $options['recorder'] ??= ViewRecorder::class;

        parent::views($options);

        $this->addMiddleware($options['middleware'] ?? AddViewInformation::class);
    }

    protected function redisCommands(array $options): void
    {
        $options['recorder'] ??= RedisCommandRecorder::class;

        parent::redisCommands($options);
    }

    protected function filesystem(array $options): void
    {
        $options['recorder'] ??= FilesystemRecorder::class;

        parent::filesystem($options);
    }

    protected function cache(array $options): void
    {
        $options['recorder'] ??= CacheRecorder::class;

        parent::cache($options);
    }

    protected function logs(array $options): void
    {
        $options['recorder'] ??= LogRecorder::class;

        parent::logs($options);
    }

    protected function externalHttp(array $options): void
    {
        $options['recorder'] ??= ExternalHttpRecorder::class;

        parent::externalHttp($options);
    }

    protected function queries(array $options): void
    {
        $options['recorder'] ??= QueryRecorder::class;

        parent::queries($options);
    }

    protected function transactions(array $options): void
    {
        $options['recorder'] ??= TransactionRecorder::class;

        parent::transactions($options);
    }
}
