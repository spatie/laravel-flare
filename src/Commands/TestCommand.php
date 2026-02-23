<?php

namespace Spatie\LaravelFlare\Commands;

use Closure;
use Composer\InstalledVersions;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\ReportableHandler;
use Laravel\SerializableClosure\Support\ReflectionClosure;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Senders\Exceptions\BadResponseCode;
use Spatie\FlareClient\Support\Tester;

class TestCommand extends Command
{
    protected $signature = 'flare:test';

    protected $description = 'Send a test notification to Flare';

    protected Repository $config;

    public function handle(Repository $config): int
    {
        $this->config = $config;

        $hasKey = $this->checkFlareKey();

        if ($this->checkFlareLogger() === false) {
            return Command::FAILURE;
        }

        if (! $hasKey) {
            return Command::FAILURE;
        }

        $this->newLine();

        $tester = app(Tester::class);

        $success = true;

        if ($config['flare.report'] === false) {
            $this->info('❌ Error reporting is disabled. Please enable it by setting the `flare.report` config value to `true` if you want to test it.');
        } else {
            $success = $this->sendTestPayload($tester, FlareEntityType::Errors);
        }

        if ($config['flare.trace'] === false) {
            $this->info('❌ Tracing is disabled. Please enable it by setting the `flare.trace` config value to `true` if you want to test it.');
        } else {
            $success = $success && $this->sendTestPayload($tester, FlareEntityType::Traces);
        }

        if ($config['flare.log'] === false) {
            $this->info('❌ Logging is disabled. Please enable it by setting the `flare.log` config value to `true` if you want to test it.');
        } else {
            $success = $success && $this->sendTestPayload($tester, FlareEntityType::Logs);
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    protected function checkFlareKey(): bool
    {
        $hasKey = ! empty($this->config->get('flare.key'));

        $message = $hasKey
            ? '✅ Flare key specified'
            : '❌ Flare key not specified. Make sure you specify a value in the `key` key of the `flare` config file.';

        $this->info($message);

        return $hasKey;
    }

    public function checkFlareLogger(): bool
    {
        $configuredCorrectly = $this->isValidReportableCallbackFlareLogger();

        if ($configuredCorrectly === false) {
            return false;
        }

        if ($this->config->get('flare.with_stack_frame_arguments') && ini_get('zend.exception_ignore_args')) {
            $this->info('⚠️ The `zend.exception_ignore_args` php ini setting is enabled. This will prevent Flare from showing stack trace arguments.');
        }

        $this->info('✅ The Flare logging driver was configured correctly.');

        return true;
    }

    protected function isValidReportableCallbackFlareLogger(): bool
    {
        $hasReportableCallbackFlareLogger = $this->hasReportableCallbackFlareLogger();

        if ($hasReportableCallbackFlareLogger) {
            return true;
        }

        $this->info('❌ The Flare logging driver was not configured correctly.');
        $this->newLine();
        $this->info('<fg=default;bg=default>Please ensure the following code is present in your `<fg=green>bootstrap/app.php</>` file:</>');
        $this->newLine();
        $this->info('<fg=default;bg=default>-><fg=green>withExceptions</>(<fg=blue>function</> (<fg=red>Exceptions</> $exceptions) {</>');
        $this->info('<fg=default;bg=default>    <fg=red>Flare</>::<fg=green>handles</>($exceptions);</>');
        $this->info('<fg=default;bg=default>})-><fg=green>create</>();</>');

        return false;
    }

    protected function hasReportableCallbackFlareLogger(): bool
    {
        try {
            $handler = app(ExceptionHandler::class);

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

    protected function sendTestPayload(
        Tester $tester,
        FlareEntityType $entityType
    ): bool {
        try {
            match ($entityType) {
                FlareEntityType::Errors => $tester->report(),
                FlareEntityType::Logs => $tester->log(),
                FlareEntityType::Traces => $tester->trace(),
            };

            $emoji = match ($entityType) {
                FlareEntityType::Errors => '⚠️',
                FlareEntityType::Logs => '📝',
                FlareEntityType::Traces => '🔍',
            };

            $entityName = ucfirst($entityType->singleName());

            $this->info("{$emoji} {$entityName} sent to Flare");

            return true;
        } catch (Exception $exception) {
            $this->warn("❌ We were unable to send a {$entityType->singleName()} to Flare. ");

            if ($exception instanceof BadResponseCode) {
                $this->info('');

                $body = $exception->response->body;

                $message = match (true) {
                    is_array($body) && isset($body['message']) => $body['message'],
                    is_string($body) && $body !== '' => $body,
                    default => 'Unknown error',
                };

                $this->warn("{$exception->response->code} - {$message}");
            } else {
                $this->warn($exception->getMessage());
            }

            $this->warn('Make sure that your key is correct and that you have a valid subscription.');
            $this->info('');
            $this->info('For more info visit the docs on https://flareapp.io/docs/integration/laravel-customizations/introduction');
            $this->info('You can see the status page of Flare at https://status.flareapp.io');
            $this->info('Flare support can be reached at support@flareapp.io');

            $this->line('');
            $this->line('Extra info');
            $this->table([], [
                ['Platform', PHP_OS],
                ['PHP', phpversion()],
                ['Laravel', app()->version()],
                ['spatie/laravel-flare', InstalledVersions::getVersion('spatie/laravel-flare')],
                ['spatie/flare-client-php', InstalledVersions::getVersion('spatie/flare-client-php')],
                ['Curl', curl_version()['version'] ?? 'Unknown'],
                ['SSL', curl_version()['ssl_version'] ?? 'Unknown'],
            ]);

            if ($this->output->isVerbose()) {
                throw $exception;
            }

            return false;
        }
    }
}
