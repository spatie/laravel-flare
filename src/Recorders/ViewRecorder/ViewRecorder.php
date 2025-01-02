<?php

namespace Spatie\LaravelFlare\Recorders\ViewRecorder;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\View;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\ViewRecorder\ViewRecorder as BaseViewRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

class ViewRecorder extends BaseViewRecorder
{
    /** @use RecordsPendingSpans<Span> */
    use RecordsPendingSpans;

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

    public function start(): void
    {
        if ($this->trace === false) {
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
        return $this->startSpan(fn () => Span::build(
            $this->tracer->currentTraceId(),
            $this->tracer->currentSpanId(),
            "View - {$viewName}",
            attributes: [
                'flare.span_type' => SpanType::View,
                'view.name' => $viewName,
                'view.loop' => $this->resolveLoop($data),
                'view.file' => $file,
                ...$attributes,
            ]
        ));
    }

    public function recordRendered(): ?Span
    {
        return $this->endSpan();
    }

    protected function wrapEnginesInEngineResolver(EngineResolver $engineResolver): void
    {
        /** @var Factory $viewFactory */
        $viewFactory = $this->app->make('view');

        $engines = array_values(array_unique($viewFactory->getExtensions()));

        $viewFactory->composer('*', function (View $view) {
            WrappedViewEngine::$currentView = $view->name();

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
