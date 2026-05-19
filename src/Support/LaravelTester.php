<?php

namespace Spatie\LaravelFlare\Support;

use Closure;
use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Exceptions\ReportableHandler;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\FlareClient\Api;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\SymfonyTester;
use Spatie\FlareClient\Time\Time;
use Spatie\LaravelFlare\Commands\TestCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LaravelTester extends SymfonyTester
{
    public function __construct(
        Api $api,
        Ids $ids,
        Time $time,
        Memory $memory,
        Resource $resource,
        ReportFactory $reportFactory,
        FlareConfig $config,
        InputInterface $input,
        OutputInterface $output,
        protected Repository $repository,
        protected Application $app,
    ) {
        parent::__construct(
            api: $api,
            ids: $ids,
            time: $time,
            memory: $memory,
            resource: $resource,
            reportFactory: $reportFactory,
            config: $config,
            input: $input,
            output: $output
        );
    }

    protected function environmentInfo(): array
    {
        return [
            ...parent::environmentInfo(),
            ['Laravel', $this->app->version()],
            ['spatie/laravel-flare', InstalledVersions::getVersion('spatie/laravel-flare') ?? 'Unknown'],
        ];
    }

    protected function buildEntryPoint(): EntryPoint
    {
        $entryPoint = new EntryPoint(
            type: EntryPointType::Cli,
            value: 'artisan flare:test',
        );

        $entryPoint->setHandler(
            handlerIdentifier: 'flare:test',
            handlerName: TestCommand::class,
            handlerType: 'php_command',
        );

        return $entryPoint;
    }

    protected function preCheckEntity(FlareEntityType $type): bool
    {
        if (! parent::preCheckEntity($type)) {
            return false;
        }

        return match ($type) {
            FlareEntityType::Errors => $this->checkErrorHandler(),
            FlareEntityType::Logs => $this->checkLogChannel(),
            default => true,
        };
    }

    protected function checkErrorHandler(): bool
    {
        if ($this->hasReportableFlareCallback()) {
            return true;
        }

        $this->writeLine('❌ The Flare error callback was not configured correctly.', self::STYLE_ERROR);
        $this->writeNewline();
        $this->io->writeln('<fg=default;bg=default>Please ensure the following code is present in your `<fg=green>bootstrap/app.php</>` file:</>');
        $this->writeNewline();
        $this->io->writeln('<fg=default;bg=default>-><fg=green>withExceptions</>(<fg=blue>function</> (<fg=red>Exceptions</> $exceptions) {</>');
        $this->io->writeln('<fg=default;bg=default>    <fg=red>Flare</>::<fg=green>handles</>($exceptions);</>');
        $this->io->writeln('<fg=default;bg=default>})-><fg=green>create</>();</>');

        return false;
    }

    protected function checkLogChannel(): bool
    {
        $channels = $this->repository->get('logging.channels', []);

        $flareChannelName = null;

        foreach ($channels as $name => $channel) {
            if (($channel['driver'] ?? null) === 'flare') {
                $flareChannelName = $name;

                break;
            }
        }

        if ($flareChannelName === null) {
            $this->writeLine('❌ No logging channel with the `flare` driver found. Please add a `flare` channel to your `config/logging.php` file.', self::STYLE_ERROR);

            return false;
        }

        $defaultChannel = $this->repository->get('logging.default');

        $isActive = $defaultChannel === $flareChannelName
            || (($channels[$defaultChannel]['driver'] ?? null) === 'stack' && in_array($flareChannelName, $channels[$defaultChannel]['channels'] ?? []));

        if (! $isActive) {
            $this->writeLine("❌ The `{$flareChannelName}` log channel exists but is not part of your default logging stack. Please add it to your `{$defaultChannel}` channel in `config/logging.php`.", self::STYLE_ERROR);

            return false;
        }

        return true;
    }

    protected function hasReportableFlareCallback(): bool
    {
        try {
            $handler = $this->app->make(ExceptionHandler::class);

            if ($handler instanceof \NunoMaduro\Collision\Adapters\Laravel\ExceptionHandler) {
                $reflection = new ReflectionProperty($handler, 'appExceptionHandler');
                $handler = $reflection->getValue($handler);
            }

            $reflection = new ReflectionProperty($handler, 'reportCallbacks');
            $reportCallbacks = $reflection->getValue($handler);

            foreach ($reportCallbacks as $reportCallback) {
                if (! $reportCallback instanceof ReportableHandler) {
                    continue;
                }

                $reflection = new ReflectionProperty($reportCallback, 'callback');
                $callback = $reflection->getValue($reportCallback);

                if (! $callback instanceof Closure) {
                    continue;
                }

                $reflection = new ReflectionClosure($callback);
                $closureReturnTypeReflection = $reflection->getReturnType();

                if (! $closureReturnTypeReflection instanceof ReflectionNamedType) {
                    continue;
                }

                return $closureReturnTypeReflection->getName() === Flare::class;
            }
        } catch (ReflectionException $exception) {
            return false;
        }

        return false;
    }
}
