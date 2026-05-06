<?php

namespace Spatie\LaravelFlare\AttributesProviders;

use Illuminate\Support\Facades\Artisan;
use Spatie\FlareClient\AttributesProviders\SymfonyInputCommandAttributesProvider;
use Symfony\Component\Console\Input\InputInterface;

class LaravelCommandAttributesProvider extends SymfonyInputCommandAttributesProvider
{
    public function __construct(
        InputInterface $input,
        string $command,
        ?string $commandClass = null,
    ) {
        parent::__construct($input, $command, $commandClass ?? $this->resolveCommandClass($command));
    }

    public function entryPointHandlerType(): ?string
    {
        return 'laravel_command';
    }

    protected function resolveCommandClass(string $command): ?string
    {
        $instance = Artisan::all()[$command] ?? null;

        return is_object($instance) ? $instance::class : null;
    }
}
