<?php

namespace Spatie\LaravelFlare\Recorders\ViewRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\View;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder as BaseViewRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class ViewRecorder extends BaseViewRecorder
{
    protected ComponentFinder $componentFinder;

    public function __construct(
        Tracer $tracer,
        protected Application $app,
        protected Dispatcher $dispatcher,
        BackTracer $backTracer,
        ArgumentReducers|null $argumentReducers,
        array $config
    ) {
        parent::__construct($tracer, $backTracer, $argumentReducers, $config);
    }

    public function boot(): void
    {
        if ($this->withTraces === false) {
            return;
        }

        $this->app->resolved('view.engine.resolver')
            ? $this->wrapEnginesInEngineResolver($this->app->make('view.engine.resolver'))
            : $this->app->afterResolving(
                'view.engine.resolver',
                fn (EngineResolver $resolver) => $this->wrapEnginesInEngineResolver($resolver)
            );
    }

    public function recordRendering(
        string $viewName,
        array $data = [],
        ?string $file = null,
        array $attributes = []
    ): ?Span {
        $attributes = [
            'view.loop' => $this->resolveLoop($data),
        ];

        $isComponent = array_key_exists('componentName', $data)
            && array_key_exists('__laravel_slots', $data);

        $isInlineComponent = str_starts_with($viewName, '__components::');

        if ($isComponent) {
            $attributes['view.component.name'] = $data['componentName'];
            $attributes['view.component.inline'] = $isInlineComponent;
        }

        if ($isComponent && $isInlineComponent && ($componentClass = $this->resolveComponentClass($data['componentName']))) {
            $attributes['view.component.class'] = $componentClass;
        }

        return parent::recordRendering($viewName, [], $file, $attributes);
    }

    protected function wrapEnginesInEngineResolver(EngineResolver $engineResolver): void
    {
        /** @var Factory $viewFactory */
        $viewFactory = $this->app->make('view');

        $engines = array_values(array_unique($viewFactory->getExtensions()));

        $viewFactory->composer('*', function (View $view) {
            WrappedViewEngine::$currentView = $view->getName();

            return $view;
        });

        foreach ($engines as $engine) {
            $originalEngine = $engineResolver->resolve($engine);

            $engineResolver->register($engine, fn () => new WrappedViewEngine(
                $this,
                $originalEngine,
            ));
        }
    }

    protected function resolveComponentClass(string $componentName): ?string
    {
        if (! isset($this->componentFinder)) {
            $this->componentFinder = new ComponentFinder();
        }

        return $this->componentFinder->resolveClassForComponent($componentName);
    }

    private function resolveLoop(array $data): ?string
    {
        if (! array_key_exists('loop', $data)) {
            return null;
        }

        $loop = $data['loop'];

        $index = $loop->index ?? '?';
        $count = $loop->count ?? '?';
        $depth = $loop->depth ?? 1;

        if (is_numeric($count)) {
            $count -= 1;
        }

        return $depth === 1
            ? "{$index}/{$count}"
            : "{$index}/{$count} (depth: {$depth})";
    }
}
