<?php

namespace Spatie\LaravelFlare\Recorders\ViewRecorder;

use Illuminate\Contracts\View\Engine;

class WrappedViewEngine implements Engine
{
    public static string $currentView = '';

    public function __construct(
        protected ViewRecorder $recorder,
        protected Engine $engine,
    ) {
    }

    public function get($path, array $data = []): string
    {
        $isInlineLivewireComponentView = str_starts_with(static::$currentView, '__components::')
            && array_key_exists('componentName', $data) === false
            && array_key_exists('__laravel_slots', $data) === false;

        if (! $isInlineLivewireComponentView) {
            $this->recorder->recordRendering(
                static::$currentView,
                $data,
                $path
            );
        }

        $rendered = $this->engine->get($path, $data);

        if (! $isInlineLivewireComponentView) {
            $this->recorder->recordRendered();
        }

        return $rendered;
    }

    public function __call(string $name, mixed $arguments): mixed
    {
        return $this->engine->{$name}(...$arguments);
    }
}
