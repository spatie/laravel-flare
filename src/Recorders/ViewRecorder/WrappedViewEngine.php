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
        $this->recorder->recordRendering(
            static::$currentView,
            $data,
            $path
        );

        $rendered = $this->engine->get($path, $data);

        $this->recorder->recordRendered();

        return $rendered;
    }

    public function __call($name, $arguments)
    {
        return $this->engine->{$name}(...$arguments);
    }
}
