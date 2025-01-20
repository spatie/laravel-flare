<?php

namespace Spatie\LaravelFlare\Recorders\DumpRecorder;

class MultiDumpHandler
{
    /** @var array<int, callable|null> */
    protected array $handlers = [];

    public function dump(mixed $value): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler) {
                $handler($value);
            }
        }
    }

    public function addHandler(callable|null $callable = null): self
    {
        $this->handlers[] = $callable;

        return $this;
    }
}
