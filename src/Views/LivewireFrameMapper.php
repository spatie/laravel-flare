<?php

namespace Spatie\LaravelFlare\Views;

use Illuminate\Contracts\Foundation\Application;
use Livewire\Compiler\CacheManager;
use Livewire\Compiler\Compiler;
use ReflectionProperty;

class LivewireFrameMapper
{
    /** @var array<string, string> compiledPath => sourcePath */
    protected array $knownPaths = [];

    /** @var array<string, bool> locations already scanned */
    protected array $scannedLocations = [];

    protected ?Compiler $compiler = null;

    public function __construct(
        protected Application $app,
    ) {
        if($this->app->bound('livewire.compiler')) {
            $this->compiler = $this->app->make('livewire.compiler');
        }
    }

    public function findCompiledFile(string $compiledPath): ?string
    {
        if (! $this->compiler) {
            return null;
        }

        if (isset($this->knownPaths[$compiledPath])) {
            return $this->knownPaths[$compiledPath];
        }

        foreach ($this->resolveViewLocations() as $location) {
            if (isset($this->scannedLocations[$location])) {
                continue;
            }

            $this->scanDirectory($location);
            $this->scannedLocations[$location] = true;

            if (isset($this->knownPaths[$compiledPath])) {
                return $this->knownPaths[$compiledPath];
            }
        }

        return null;
    }


    /** @return array<string> */
    protected function resolveViewLocations(): array
    {
        if (! $this->app->bound('livewire.finder')) {
            return [];
        }

        $finder = $this->app->make('livewire.finder');

        try {
            $reflection = new ReflectionProperty($finder, 'viewLocations');

            return $reflection->getValue($finder);
        } catch (\ReflectionException) {
            return [];
        }
    }

    protected function scanDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $cacheManager = $this->compiler->getCacheManager();

        $files = glob("{$directory}/*.blade.php") ?: [];

        foreach ($files as $file) {
            $realPath = realpath($file);

            if ($realPath === false) {
                continue;
            }

            $classPath = realpath($cacheManager->getClassPath($realPath));

            if ($classPath === false) {
                continue;
            }

            $this->knownPaths[$classPath] = $realPath;
        }

        $subdirs = glob("{$directory}/*/", GLOB_ONLYDIR) ?: [];

        foreach ($subdirs as $subdir) {
            $this->scanDirectory($subdir);
        }
    }
}
