<?php

namespace Spatie\LaravelFlare\Views;

use Illuminate\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewException;
use ReflectionProperty;
use Spatie\ErrorSolutions\Contracts\ProvidesSolution;
use Spatie\LaravelFlare\Exceptions\ViewException as FlareViewException;
use Spatie\LaravelFlare\Exceptions\ViewExceptionWithSolution;
use Throwable;

class ViewExceptionMapper
{
    public function __construct(
        protected ViewFrameMapper $viewFrameMapper
    ) {
    }

    public function map(ViewException $viewException): FlareViewException
    {
        $baseException = $this->getRealException($viewException);

        if ($baseException instanceof FlareViewException) {
            return $baseException;
        }

        preg_match('/\(View: (?P<path>.*?)\)/', $viewException->getMessage(), $matches);

        $compiledViewPath = $matches['path'];

        $exception = $this->createException($baseException);

        if ($baseException instanceof ProvidesSolution) {
            /** @var ViewExceptionWithSolution $exception */
            $exception->setSolution($baseException->getSolution());
        }

        $this->modifyViewsInTrace($exception);

        $exception->setView($compiledViewPath);
        $exception->setViewData($this->getViewData($exception));

        return $exception;
    }

    protected function createException(Throwable $baseException): FlareViewException
    {
        $viewExceptionClass = $baseException instanceof ProvidesSolution
            ? ViewExceptionWithSolution::class
            : FlareViewException::class;

        $viewFile = $this->viewFrameMapper->findCompiledView($baseException->getFile());
        $file = $viewFile ?? $baseException->getFile();
        $line = $viewFile ? $this->viewFrameMapper->getBladeLineNumber($file, $baseException->getLine()) : $baseException->getLine();

        return new $viewExceptionClass(
            $baseException->getMessage(),
            0,
            1,
            $file,
            $line,
            $baseException
        );
    }

    protected function modifyViewsInTrace(FlareViewException $exception): void
    {
        $viewIndex = null;

        $trace = $exception->getPrevious()->getTrace();

        $trace = array_map(function ($frame, $index) use (&$viewIndex) {
            if ($originalPath = $this->viewFrameMapper->findCompiledView(Arr::get($frame, 'file', ''))) {
                $frame['file'] = $originalPath;
                $frame['line'] = $this->viewFrameMapper->getBladeLineNumber($frame['file'], $frame['line']);

                if ($viewIndex === null) {
                    $viewIndex = $index;
                }
            }

            return $frame;
        }, $trace, array_keys($trace));

        if ($viewIndex !== null && str_ends_with($exception->getFile(), '.blade.php')) {
            $trace = array_slice($trace, $viewIndex + 1); // Remove all traces before the view
        }

        $traceProperty = new ReflectionProperty('Exception', 'trace');
        $traceProperty->setAccessible(true);
        $traceProperty->setValue($exception, $trace);
    }

    /**
     * Look at the previous exceptions to find the original exception.
     * This is usually the first Exception that is not a ViewException.
     */
    protected function getRealException(Throwable $exception): Throwable
    {
        $rootException = $exception->getPrevious() ?? $exception;

        while ($rootException instanceof ViewException && $rootException->getPrevious()) {
            $rootException = $rootException->getPrevious();
        }

        return $rootException;
    }

    protected function getViewData(Throwable $exception): array
    {
        foreach ($exception->getTrace() as $frame) {
            if (Arr::get($frame, 'class') === PhpEngine::class) {
                $data = Arr::get($frame, 'args.1', []);

                return $this->filterViewData($data);
            }
        }

        return [];
    }

    protected function filterViewData(array $data): array
    {
        // By default, Laravel views get two data keys:
        // __env and app. We try to filter them out.
        return array_filter($data, function ($value, $key) {
            if ($key === 'app') {
                return ! $value instanceof Application;
            }

            return $key !== '__env';
        }, ARRAY_FILTER_USE_BOTH);
    }
}
