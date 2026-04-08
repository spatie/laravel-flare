<?php

namespace Spatie\LaravelFlare\Views;

use Illuminate\Contracts\Foundation\Application;
use ReflectionProperty;
use Spatie\LaravelFlare\Support\LivewireComponentFinder;

class LivewireSingleFileComponentFrameMapper
{
    /** @var array<string, string> hash => sourcePath */
    protected array $compiledPathMap;

    public function __construct(
        protected Application $app,
        protected LivewireComponentFinder $livewireComponentFinder,
    ) {
    }

    public function findSourcePath(string $path): ?string
    {
        $this->buildMap();

        foreach ($this->compiledPathMap as $hash => $sourcePath) {
            if (str_contains($path, DIRECTORY_SEPARATOR . "{$hash}.")) {
                return $sourcePath;
            }
        }

        return null;
    }

    protected function buildMap(): void
    {
        if (isset($this->compiledPathMap)) {
            return;
        }

        $this->compiledPathMap = [];

        $factory = $this->app->make('livewire.factory');

        try {
            $reflection = new ReflectionProperty($factory, 'resolvedComponentCache');

            $resolvedComponents = $reflection->getValue($factory);
        } catch (\ReflectionException) {
            return;
        }

        foreach ($resolvedComponents as $name => $class) {
            if (! str_contains($class, "\x00")) {
                continue;
            }

            $compiledClassPath = preg_replace('/:\d+\$.*$/', '', substr($class, strpos($class, "\x00") + 1));

            if (! $compiledClassPath) {
                continue;
            }

            $sourcePath = $this->livewireComponentFinder->findSingleFileComponentFile($name);

            if ($sourcePath === null) {
                continue;
            }

            if (preg_match('/[\/\\\\]([a-f0-9]+)\.php$/', $compiledClassPath, $matches)) {
                $this->compiledPathMap[$matches[1]] = $sourcePath;
            }
        }
    }
}
