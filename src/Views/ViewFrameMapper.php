<?php

namespace Spatie\LaravelFlare\Views;

use Illuminate\Contracts\View\Engine;
use ReflectionClass;
use ReflectionProperty;

class ViewFrameMapper
{
    protected Engine $compilerEngine;

    protected BladeSourceMapCompiler $bladeSourceMapCompiler;

    protected array $knownPaths;

    public function __construct(BladeSourceMapCompiler $bladeSourceMapCompiler)
    {
        $resolver = app('view.engine.resolver');

        $this->compilerEngine = $resolver->resolve('blade');

        $this->bladeSourceMapCompiler = $bladeSourceMapCompiler;
    }

    /**
     * @param array<array> $trace
     *
     * @return array{viewIndex: int|null, trace: array<array>}
     */
    public function mapExceptionTrace(
        array $trace
    ): array {


        return [
            'viewIndex' => $viewIndex,
            'trace' => $trace,
        ];
    }

    public function findCompiledView(string $compiledPath): ?string
    {
        $this->knownPaths ??= $this->getKnownPaths();

        return $this->knownPaths[$compiledPath] ?? null;
    }

    public function getBladeLineNumber(string $view, int $compiledLineNumber): int
    {
        return $this->bladeSourceMapCompiler->detectLineNumber($view, $compiledLineNumber);
    }

    protected function getKnownPaths(): array
    {
        $compilerEngineReflection = new ReflectionClass($this->compilerEngine);

        if (! $compilerEngineReflection->hasProperty('lastCompiled') && $compilerEngineReflection->hasProperty('engine')) {
            $compilerEngine = $compilerEngineReflection->getProperty('engine');
            $compilerEngine->setAccessible(true);
            $compilerEngine = $compilerEngine->getValue($this->compilerEngine);
            $lastCompiled = new ReflectionProperty($compilerEngine, 'lastCompiled');
            $lastCompiled->setAccessible(true);
            $lastCompiled = $lastCompiled->getValue($compilerEngine);
        } else {
            $lastCompiled = $compilerEngineReflection->getProperty('lastCompiled');
            $lastCompiled->setAccessible(true);
            $lastCompiled = $lastCompiled->getValue($this->compilerEngine);
        }

        $knownPaths = [];
        foreach ($lastCompiled as $lastCompiledPath) {
            $compiledPath = $this->compilerEngine->getCompiler()->getCompiledPath($lastCompiledPath);

            $knownPaths[realpath($compiledPath ?? $lastCompiledPath)] = realpath($lastCompiledPath);
        }

        return $knownPaths;
    }
}
